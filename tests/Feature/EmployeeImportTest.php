<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Build an uploaded CSV file from rows.
 *
 * @param  array<int, array<int, string>>  $rows
 */
function csvUpload(array $rows, string $name = 'employees.csv'): UploadedFile
{
    $content = implode("\n", array_map(
        fn (array $row): string => implode(',', array_map(fn (string $cell): string => str_contains($cell, ',') ? "\"{$cell}\"" : $cell, $row)),
        $rows,
    ));

    return UploadedFile::fake()->createWithContent($name, $content);
}

/**
 * Build an uploaded Excel file from rows, keeping cell types such as dates and numbers.
 *
 * @param  array<int, array<int, mixed>>  $rows
 */
function xlsxUpload(array $rows): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }
    $writer->close();

    return new UploadedFile($path, 'employees.xlsx', null, null, true);
}

const IMPORT_HEADER = ['Company', 'Employee Number', 'First Name', 'Last Name', 'Mobile Number', 'Birth Date', 'Sex', 'Status'];

test('adds employees from a CSV file and normalizes their details', function () {
    $response = $this->actingAs(User::factory()->medicalStaff()->create())->post(route('employees.import.store'), [
        'file' => csvUpload([
            IMPORT_HEADER,
            ['GGHI', 'G-001', 'Ana', 'Santos', '0917 123 4567', '03/15/1990', 'F', 'Active'],
            ['tripple2', 'T-001', 'Juan', 'Dela Cruz', '', '1985-01-02', 'Male', ''],
        ]),
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('employees.index'));
    expect(Employee::firstWhere('employee_number', 'G-001'))
        ->company->name->toBe('GGHI')
        ->phone->toBe('+639171234567')
        ->birth_date->toDateString()->toBe('1990-03-15')
        ->sex->toBe('female')
        ->is_active->toBeTrue();
    expect(Employee::firstWhere('employee_number', 'T-001'))
        ->company->name->toBe('Tripple2')
        ->is_active->toBeTrue();
    expect(AuditLog::query()->where('action', AuditAction::Imported)->sole()->description)
        ->toContain('2 added, 0 updated');
});

test('reads Excel files, including real date cells and numbers that lost their leading zero', function () {
    $this->actingAs(User::factory()->medicalStaff()->create())->post(route('employees.import.store'), [
        'file' => xlsxUpload([
            IMPORT_HEADER,
            ['GSAC Main', 'S-001', 'Maria', 'Reyes', 9171234567, new DateTimeImmutable('1992-07-04'), 'Female', 'Active'],
        ]),
    ])->assertSessionHasNoErrors();

    expect(Employee::sole())
        ->phone->toBe('+639171234567')
        ->birth_date->toDateString()->toBe('1992-07-04');
});

test('recognizes common heading variations', function () {
    $this->actingAs(User::factory()->medicalStaff()->create())->post(route('employees.import.store'), [
        'file' => csvUpload([
            ['Branch', 'Emp No.', 'Given Name', 'Surname', 'Cellphone'],
            ['GGHI', 'G-001', 'Ana', 'Santos', '09171234567'],
        ]),
    ])->assertSessionHasNoErrors();

    expect(Employee::sole()->employee_number)->toBe('G-001');
});

test('updates existing employees by employee number without erasing details left blank', function () {
    $company = Company::firstWhere('name', 'GGHI');
    $employee = Employee::factory()->for($company)->create([
        'employee_number' => 'G-001',
        'first_name' => 'Ana',
        'department' => 'Finance',
    ]);

    $this->actingAs(User::factory()->medicalStaff()->create())->post(route('employees.import.store'), [
        'file' => csvUpload([
            ['Company', 'Employee Number', 'First Name', 'Last Name', 'Department', 'Status'],
            ['GGHI', 'G-001', 'Anna', 'Santos', '', 'Resigned'],
        ]),
    ])->assertSessionHasNoErrors();

    expect($employee->fresh())
        ->first_name->toBe('Anna')
        ->department->toBe('Finance')
        ->is_active->toBeFalse();
    $this->assertDatabaseCount('employees', 1);
});

test('imports nothing and lists every bad row when any row is invalid', function () {
    $response = $this->actingAs(User::factory()->medicalStaff()->create())->post(route('employees.import.store'), [
        'file' => csvUpload([
            IMPORT_HEADER,
            ['GGHI', 'G-001', 'Ana', 'Santos', '', '', '', ''],
            ['Acme Corp', 'G-002', 'Juan', 'Cruz', '', '', '', ''],
            ['GGHI', 'G-003', '', 'Reyes', '(02) 8123 4567', '31/31/1990', 'X', 'Maybe'],
            ['GGHI', 'G-001', 'Ana', 'Duplicate', '', '', '', ''],
        ]),
    ]);

    $response->assertSessionHasErrors([
        'file' => '3 rows need fixing. Nothing was imported.',
        'row_3' => 'Row 3: Unknown company "Acme Corp". Use one of: GGHI, GSAC Main, Tripple2.',
    ]);
    $errors = session('errors')->getBag('default');
    expect($errors->first('row_4'))
        ->toContain('first name field is required')
        ->toContain('Enter a Philippine mobile number')
        ->toContain('Birth date is not a date')
        ->toContain('Sex must be Male or Female')
        ->toContain('Status must be Active or Inactive');
    expect($errors->first('row_5'))->toBe('Row 5: Employee number G-001 also appears on row 2.');
    $this->assertDatabaseCount('employees', 0);
});

test('rejects a file missing required columns', function () {
    $response = $this->actingAs(User::factory()->medicalStaff()->create())->post(route('employees.import.store'), [
        'file' => csvUpload([['Name', 'Department'], ['Ana Santos', 'Finance']]),
    ]);

    $response->assertSessionHasErrors([
        'file' => 'The header row is missing these columns: Company, Employee Number, First Name, Last Name. Download the template to see the expected headings.',
    ]);
});

test('rejects files that are not Excel or CSV', function () {
    $response = $this->actingAs(User::factory()->medicalStaff()->create())->post(route('employees.import.store'), [
        'file' => UploadedFile::fake()->create('employees.pdf', 10, 'application/pdf'),
    ]);

    $response->assertSessionHasErrors(['file' => 'The file must be an Excel (.xlsx) or CSV file.']);
});

describe('HR', function () {
    test('imports into their own company without needing a Company column', function () {
        $company = Company::firstWhere('name', 'Tripple2');

        $this->actingAs(User::factory()->hr($company)->create())->post(route('employees.import.store'), [
            'file' => csvUpload([['Employee Number', 'First Name', 'Last Name'], ['T-001', 'Ana', 'Santos']]),
        ])->assertSessionHasNoErrors();

        expect(Employee::sole()->company_id)->toBe($company->id);
    });

    test('cannot import employees into another company', function () {
        $response = $this->actingAs(User::factory()->hr(Company::firstWhere('name', 'Tripple2'))->create())
            ->post(route('employees.import.store'), [
                'file' => csvUpload([IMPORT_HEADER, ['GGHI', 'G-001', 'Ana', 'Santos', '', '', '', '']]),
            ]);

        $response->assertSessionHasErrors(['row_2' => 'Row 2: You can only import employees of Tripple2.']);
        $this->assertDatabaseCount('employees', 0);
    });

    test('cannot overwrite another company\'s employee through a matching number', function () {
        $employee = Employee::factory()->for(Company::firstWhere('name', 'GGHI'))->create(['employee_number' => 'X-001', 'first_name' => 'Original']);

        $response = $this->actingAs(User::factory()->hr(Company::firstWhere('name', 'Tripple2'))->create())
            ->post(route('employees.import.store'), [
                'file' => csvUpload([['Employee Number', 'First Name', 'Last Name'], ['X-001', 'Hijacked', 'Name']]),
            ]);

        $response->assertSessionHasErrors('row_2');
        expect($employee->fresh()->first_name)->toBe('Original');
    });
});

test('forbids employees from importing', function () {
    $response = $this->actingAs(User::factory()->create())->post(route('employees.import.store'), [
        'file' => csvUpload([IMPORT_HEADER]),
    ]);

    $response->assertForbidden();
});

test('downloads the template in either format', function (string $format) {
    $response = $this->actingAs(User::factory()->medicalStaff()->create())
        ->get(route('employees.import.template', ['format' => $format]));

    $response->assertOk()->assertDownload("employee-import-template.{$format}");
})->with(['xlsx', 'csv']);
