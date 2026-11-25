<?php

namespace App\Actions;

use App\Concerns\EmployeeValidationRules;
use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use SplFileObject;
use Throwable;

/**
 * Adds or updates employees from an Excel (.xlsx) or CSV list. The import is all-or-nothing:
 * every row is checked first, and nothing is saved if any row has a problem.
 */
class ImportEmployees
{
    use EmployeeValidationRules;

    /**
     * The most data rows one file may contain.
     */
    public const MAX_ROWS = 1000;

    /**
     * The most row problems reported back at once.
     */
    private const MAX_REPORTED_ERRORS = 50;

    /**
     * Column headings people use for each field, compared after lowercasing and removing punctuation.
     *
     * @var array<string, array<int, string>>
     */
    public const COLUMNS = [
        'company' => ['company', 'company name', 'branch'],
        'employee_number' => ['employee number', 'employee no', 'emp no', 'employee id', 'id number', 'employee number'],
        'first_name' => ['first name', 'firstname', 'given name'],
        'last_name' => ['last name', 'lastname', 'surname', 'family name'],
        'department' => ['department', 'dept'],
        'position' => ['position', 'job title', 'designation'],
        'email' => ['email', 'email address', 'e mail'],
        'phone' => ['mobile number', 'mobile', 'mobile no', 'cellphone', 'cellphone number', 'cp number', 'phone', 'phone number', 'contact number'],
        'birth_date' => ['birth date', 'birthdate', 'date of birth', 'birthday'],
        'sex' => ['sex', 'gender'],
        'hired_on' => ['date hired', 'hire date', 'hired on', 'date started', 'start date'],
        'status' => ['status', 'employment status', 'active'],
    ];

    /**
     * Accepted spellings for dates typed as text, tried in order.
     *
     * @var array<int, string>
     */
    private const DATE_FORMATS = ['Y-m-d', 'm/d/Y', 'n/j/Y', 'm-d-Y', 'M j, Y', 'F j, Y', 'j M Y', 'j F Y'];

    /**
     * The user running the import; HR users are limited to their own company.
     */
    private User $importer;

    /**
     * Import the file and return how many employees were added and updated.
     *
     * @return array{created: int, updated: int}
     *
     * @throws ValidationException when the file cannot be read or any row is invalid
     */
    public function handle(UploadedFile $file, User $importer): array
    {
        $this->importer = $importer;

        $rows = $this->readRows($file);
        $prepared = $this->validateRows($rows);

        $counts = DB::transaction(function () use ($prepared): array {
            $counts = ['created' => 0, 'updated' => 0];

            foreach ($prepared as ['existing' => $existing, 'attributes' => $attributes]) {
                if ($existing === null) {
                    Employee::query()->create(['is_active' => true, ...$attributes]);
                    $counts['created']++;
                } else {
                    // Blank cells leave what is already on file untouched.
                    $existing->update(array_filter($attributes, fn (mixed $value): bool => $value !== null));
                    $counts['updated']++;
                }
            }

            return $counts;
        });

        AuditLog::record(
            AuditAction::Imported,
            null,
            "Imported employees from \"{$file->getClientOriginalName()}\": {$counts['created']} added, {$counts['updated']} updated",
        );

        return $counts;
    }

    /**
     * Read the first sheet into rows keyed by field name, remembering each row's line number.
     *
     * @return array<int, array<string, mixed>> keyed by spreadsheet row number
     *
     * @throws ValidationException
     */
    private function readRows(UploadedFile $file): array
    {
        $extension = Str::lower($file->getClientOriginalExtension());
        $reader = $extension === 'xlsx'
            ? new XlsxReader
            : new CsvReader(new CsvOptions(FIELD_DELIMITER: $this->detectCsvDelimiter($file->getRealPath())));

        try {
            $reader->open($file->getRealPath());
            $rows = [];
            $fieldsByColumn = null;
            $rowNumber = 0;

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;
                    $values = $row->toArray();

                    if ($this->isBlankRow($values)) {
                        continue;
                    }

                    if ($fieldsByColumn === null) {
                        $fieldsByColumn = $this->mapHeader($values);

                        continue;
                    }

                    $record = [];
                    foreach ($fieldsByColumn as $column => $field) {
                        $record[$field] = $values[$column] ?? null;
                    }
                    $rows[$rowNumber] = $record;

                    if (count($rows) > self::MAX_ROWS) {
                        throw ValidationException::withMessages([
                            'file' => 'The file has more than '.self::MAX_ROWS.' employees. Split it into smaller files.',
                        ]);
                    }
                }

                break;
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages(['file' => 'The file could not be read. Save it as .xlsx or .csv and try again.']);
        } finally {
            $reader->close();
        }

        if ($fieldsByColumn === null || $rows === []) {
            throw ValidationException::withMessages(['file' => 'The file has no employee rows below the header row.']);
        }

        return $rows;
    }

    /**
     * Work out which field each column holds from the header row.
     *
     * @param  array<int, mixed>  $header
     * @return array<int, string> column index => field name
     *
     * @throws ValidationException when a required column is missing
     */
    private function mapHeader(array $header): array
    {
        $fieldsByColumn = [];

        foreach ($header as $column => $heading) {
            $normalized = Str::of((string) $heading)->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();

            foreach (self::COLUMNS as $field => $aliases) {
                if (in_array($normalized, $aliases, true) && ! in_array($field, $fieldsByColumn, true)) {
                    $fieldsByColumn[$column] = $field;
                }
            }
        }

        $required = [
            ...($this->importer->hasRole(Role::Hr) ? [] : ['company']),
            'employee_number',
            'first_name',
            'last_name',
        ];

        $missing = array_diff($required, $fieldsByColumn);

        if ($missing !== []) {
            $labels = array_map(fn (string $field): string => Str::headline($field), $missing);

            throw ValidationException::withMessages([
                'file' => 'The header row is missing these columns: '.implode(', ', $labels).'. Download the template to see the expected headings.',
            ]);
        }

        return $fieldsByColumn;
    }

    /**
     * Check every row and turn it into employee attributes. Throws with every problem found when any row is invalid.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{existing: Employee|null, attributes: array<string, mixed>}>
     *
     * @throws ValidationException
     */
    private function validateRows(array $rows): array
    {
        $companies = Company::query()->get(['id', 'name'])->keyBy(fn (Company $company): string => Str::lower($company->name));
        $existingByNumber = Employee::query()
            ->whereIn('employee_number', array_filter(array_map(fn (array $row): ?string => $this->text($row['employee_number'] ?? null), $rows)))
            ->get()
            ->keyBy('employee_number');

        $prepared = [];
        $errors = [];
        $seenNumbers = [];

        foreach ($rows as $rowNumber => $row) {
            $attributes = $this->normalize($row);
            $rowErrors = [];

            $companyErrorCount = count($rowErrors);
            $attributes['company_id'] = $this->resolveCompanyId($attributes['company'], $companies, $rowErrors);
            $hasCompanyError = count($rowErrors) > $companyErrorCount;
            unset($attributes['company']);

            // A blank status means "leave as is" (or Active for new employees), not "invalid".
            if ($attributes['is_active'] === null) {
                unset($attributes['is_active']);
            }

            $number = $attributes['employee_number'];
            $existing = $number === null ? null : $existingByNumber->get($number);

            if ($number !== null && isset($seenNumbers[$number])) {
                $rowErrors[] = "Employee number {$number} also appears on row {$seenNumbers[$number]}.";
            }
            if ($number !== null) {
                $seenNumbers[$number] = $rowNumber;
            }

            if ($existing !== null && ! $this->importer->can('update', $existing)) {
                $rowErrors[] = "Employee number {$number} belongs to another company.";
            }

            $validator = Validator::make($attributes, $this->employeeRules($existing?->id), [
                ...$this->employeeMessages(),
                'company_id.required' => 'Company is blank.',
                'is_active.boolean' => 'Status must be Active or Inactive.',
                'sex.in' => 'Sex must be Male or Female.',
                'birth_date.date' => 'Birth date is not a date (use YYYY-MM-DD or MM/DD/YYYY).',
                'hired_on.date' => 'Date hired is not a date (use YYYY-MM-DD or MM/DD/YYYY).',
            ]);
            $messages = $validator->errors();
            if ($hasCompanyError) {
                // The company problem is already explained; "Company is blank" would only confuse.
                $messages->forget('company_id');
            }
            array_push($rowErrors, ...$messages->all());

            if ($rowErrors !== []) {
                $errors["row_{$rowNumber}"] = "Row {$rowNumber}: ".implode(' ', array_unique($rowErrors));

                continue;
            }

            $prepared[] = ['existing' => $existing, 'attributes' => $attributes];
        }

        if ($errors !== []) {
            $total = count($errors);

            throw ValidationException::withMessages([
                'file' => "{$total} ".Str::plural('row', $total).' need fixing. Nothing was imported.',
                ...array_slice($errors, 0, self::MAX_REPORTED_ERRORS, true),
            ]);
        }

        return $prepared;
    }

    /**
     * Find the company a row belongs to. HR users always import into their own company.
     *
     * @param  Collection<string, Company>  $companies
     * @param  array<int, string>  $rowErrors
     */
    private function resolveCompanyId(?string $name, Collection $companies, array &$rowErrors): ?int
    {
        if ($this->importer->hasRole(Role::Hr)) {
            $ownCompany = $companies->first(fn (Company $company): bool => $company->id === $this->importer->company_id);

            if ($name !== null && Str::lower($name) !== Str::lower((string) $ownCompany?->name)) {
                $rowErrors[] = "You can only import employees of {$ownCompany?->name}.";
            }

            return $this->importer->company_id;
        }

        if ($name === null) {
            return null;
        }

        $company = $companies->get(Str::lower($name));

        if ($company === null) {
            $rowErrors[] = "Unknown company \"{$name}\". Use one of: ".$companies->pluck('name')->sort()->implode(', ').'.';
        }

        return $company?->id;
    }

    /**
     * Convert spreadsheet cells into employee attribute values.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalize(array $row): array
    {
        $phone = $this->text($row['phone'] ?? null);
        if ($phone !== null && preg_match('/^9\d{9}$/', $phone) === 1) {
            // Excel drops the leading zero of mobile numbers stored as numbers.
            $phone = '0'.$phone;
        }

        return [
            'company' => $this->text($row['company'] ?? null),
            'employee_number' => $this->text($row['employee_number'] ?? null),
            'first_name' => $this->text($row['first_name'] ?? null),
            'last_name' => $this->text($row['last_name'] ?? null),
            'department' => $this->text($row['department'] ?? null),
            'position' => $this->text($row['position'] ?? null),
            'email' => $this->text($row['email'] ?? null),
            'phone' => $phone,
            'birth_date' => $this->date($row['birth_date'] ?? null),
            'sex' => match (Str::lower((string) $this->text($row['sex'] ?? null))) {
                'm', 'male' => 'male',
                'f', 'female' => 'female',
                '' => null,
                default => (string) $this->text($row['sex']),
            },
            'hired_on' => $this->date($row['hired_on'] ?? null),
            'is_active' => match (Str::lower((string) $this->text($row['status'] ?? null))) {
                '' => null,
                'active', 'yes', 'y', '1', 'true', 'employed' => true,
                'inactive', 'no', 'n', '0', 'false', 'resigned', 'separated', 'terminated' => false,
                default => (string) $this->text($row['status']),
            },
        ];
    }

    /**
     * Turn a cell into trimmed text, or null when empty. Whole numbers lose Excel's ".0".
     */
    private function text(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_float($value) && floor($value) === $value) {
            $value = sprintf('%.0f', $value);
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * Turn a date cell (a real Excel date or text like 03/15/1990) into Y-m-d. Unrecognized text is
     * passed through so validation reports it.
     */
    private function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        // Excel stores dates as days since 1899-12-30; unformatted date cells arrive as that number.
        if ((is_int($value) || is_float($value)) && $value >= 1 && $value < 100000) {
            return (new DateTimeImmutable('1899-12-30'))->modify('+'.(int) $value.' days')->format('Y-m-d');
        }

        $text = $this->text($value);

        if ($text === null) {
            return null;
        }

        foreach (self::DATE_FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat("!{$format}", $text);

            // Round-tripping rejects overflowing dates such as 02/30/1990.
            if ($date !== false && $date->format($format) === $text) {
                return $date->format('Y-m-d');
            }
        }

        return $text;
    }

    /**
     * Determine whether every cell in the row is empty.
     *
     * @param  array<int, mixed>  $values
     */
    private function isBlankRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($this->text($value) !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Pick comma or semicolon (used by Excel in some regional settings) from the first line.
     */
    private function detectCsvDelimiter(string $path): string
    {
        $firstLine = (string) (new SplFileObject($path))->fgets();

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }
}
