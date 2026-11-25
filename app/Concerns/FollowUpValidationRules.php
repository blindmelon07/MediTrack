<?php

namespace App\Concerns;

use App\Enums\FollowUpStatus;
use App\Models\Employee;
use App\Models\HealthCheck;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait FollowUpValidationRules
{
    /**
     * Get the validation rules used to validate follow-up records.
     *
     * @return array<string, array<int, ValidationRule|string|object>>
     */
    protected function followUpRules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists(Employee::class, 'id')],
            'health_check_id' => [
                'nullable',
                'integer',
                Rule::exists(HealthCheck::class, 'id')->where('employee_id', $this->integer('employee_id')),
            ],
            'assigned_to' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
            'reason' => ['required', 'string', 'max:255'],
            'due_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Get the validation rules for recording the outcome of a follow-up.
     *
     * @return array<string, array<int, ValidationRule|string|object>>
     */
    protected function followUpOutcomeRules(): array
    {
        return [
            'status' => ['required', Rule::enum(FollowUpStatus::class)],
            'completed_on' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * Get the custom messages for follow-up validation.
     *
     * @return array<string, string>
     */
    protected function followUpMessages(): array
    {
        return [
            'health_check_id.exists' => 'The selected health check does not belong to this employee.',
        ];
    }
}
