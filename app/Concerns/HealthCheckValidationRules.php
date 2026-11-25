<?php

namespace App\Concerns;

use App\Enums\HealthCheckResult;
use App\Enums\HealthCheckType;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait HealthCheckValidationRules
{
    /**
     * Get the validation rules used to validate health check records.
     *
     * @return array<string, array<int, ValidationRule|string|object>>
     */
    protected function healthCheckRules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists(Employee::class, 'id')],
            'type' => ['required', Rule::enum(HealthCheckType::class)],
            'checked_on' => ['required', 'date', 'before_or_equal:today'],
            'examined_by' => ['nullable', 'string', 'max:255'],
            'blood_pressure' => ['nullable', 'string', 'regex:/^\d{2,3}\/\d{2,3}$/'],
            'heart_rate' => ['nullable', 'integer', 'between:20,250'],
            'temperature' => ['nullable', 'numeric', 'between:30,45'],
            'weight_kg' => ['nullable', 'numeric', 'between:1,500'],
            'height_cm' => ['nullable', 'numeric', 'between:30,260'],
            'findings' => ['nullable', 'string', 'max:5000'],
            'diagnosis' => ['nullable', 'string', 'max:5000'],
            'recommendations' => ['nullable', 'string', 'max:5000'],
            'result' => ['required', Rule::enum(HealthCheckResult::class)],
        ];
    }

    /**
     * Get the validation rules for uploaded health check files such as lab results.
     *
     * @return array<string, array<int, string>>
     */
    protected function attachmentRules(bool $required = false): array
    {
        return [
            'attachments' => [$required ? 'required' : 'nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * Get the custom messages for health check validation.
     *
     * @return array<string, string>
     */
    protected function healthCheckMessages(): array
    {
        return [
            'blood_pressure.regex' => 'Enter blood pressure as systolic/diastolic, for example 120/80.',
            'checked_on.before_or_equal' => 'The check date cannot be in the future.',
            'attachments.*.mimes' => 'Attachments must be PDF, JPG, or PNG files.',
            'attachments.*.max' => 'Each attachment must be 10 MB or smaller.',
        ];
    }
}
