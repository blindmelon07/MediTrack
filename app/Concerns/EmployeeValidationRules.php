<?php

namespace App\Concerns;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait EmployeeValidationRules
{
    /**
     * Get the validation rules used to validate employee records.
     *
     * @return array<string, array<int, ValidationRule|string|object>>
     */
    protected function employeeRules(?int $employeeId = null): array
    {
        return [
            'company_id' => ['required', 'integer', Rule::exists(Company::class, 'id')],
            'employee_number' => [
                'required',
                'string',
                'max:50',
                $employeeId === null
                    ? Rule::unique(Employee::class)
                    : Rule::unique(Employee::class)->ignore($employeeId),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'sex' => ['nullable', Rule::in(['male', 'female'])],
            'hired_on' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Pin HR users to their own company so they cannot file employees under another one.
     */
    protected function assignHrCompany(): void
    {
        $user = $this->user();

        if ($user !== null && $user->hasRole(Role::Hr)) {
            $this->merge(['company_id' => $user->company_id]);
        }
    }

    /**
     * Get the custom messages for employee validation.
     *
     * @return array<string, string>
     */
    protected function employeeMessages(): array
    {
        return [
            'company_id.required' => 'Choose the company the employee works for.',
        ];
    }
}
