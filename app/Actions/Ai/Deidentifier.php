<?php

namespace App\Actions\Ai;

use App\Models\Employee;

/**
 * Removes what could identify a person before text is sent to the AI service:
 * the employee's name, employee number, email and phone, plus any email address
 * or Philippine phone number written into free text.
 */
class Deidentifier
{
    public const string PATIENT = '[patient]';

    public const string REMOVED = '[removed]';

    /**
     * Replace identifying details of the employee, and any contact details, in free text.
     */
    public function scrub(?string $text, Employee $employee): ?string
    {
        if ($text === null || trim($text) === '') {
            return $text;
        }

        foreach ($this->identifiersOf($employee) as $identifier) {
            $text = preg_replace('/(?<![\pL\pN])'.preg_quote($identifier, '/').'(?![\pL\pN])/iu', self::PATIENT, $text) ?? $text;
        }

        return $this->scrubContactDetails($text);
    }

    /**
     * Replace email addresses and phone numbers in free text.
     */
    public function scrubContactDetails(string $text): string
    {
        $text = preg_replace('/[\w.+-]+@[\w-]+(\.[\w-]+)+/u', self::REMOVED, $text) ?? $text;

        // Philippine mobile and landline numbers, with or without +63, spaces or dashes.
        return preg_replace('/(?<!\d)(\+?63|0)[\s-]?\d{2,3}[\s-]?\d{3}[\s-]?\d{4}(?!\d)/', self::REMOVED, $text) ?? $text;
    }

    /**
     * Describe the employee without identifying them: age in years and sex.
     *
     * @return array{age: int|null, sex: string|null}
     */
    public function profile(Employee $employee): array
    {
        return [
            'age' => $employee->birth_date?->age,
            'sex' => $employee->sex,
        ];
    }

    /**
     * Get the strings that identify the employee, longest first so full names are replaced before parts.
     *
     * @return array<int, string>
     */
    private function identifiersOf(Employee $employee): array
    {
        $identifiers = [
            "{$employee->first_name} {$employee->last_name}",
            "{$employee->last_name}, {$employee->first_name}",
            "{$employee->last_name} {$employee->first_name}",
            $employee->employee_number,
            $employee->email,
            $employee->phone,
            ...preg_split('/\s+/', "{$employee->first_name} {$employee->last_name}") ?: [],
        ];

        $identifiers = array_filter(
            array_map(fn (?string $identifier): string => trim((string) $identifier), $identifiers),
            fn (string $identifier): bool => mb_strlen($identifier) >= 2,
        );

        $identifiers = array_unique($identifiers);
        usort($identifiers, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return $identifiers;
    }
}
