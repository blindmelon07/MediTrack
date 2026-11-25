<?php

namespace App\Http\Requests;

use App\Concerns\HealthCheckValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHealthCheckRequest extends FormRequest
{
    use HealthCheckValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $skipFollowUp = Rule::excludeIf(! $this->boolean('schedule_follow_up'));

        return [
            ...$this->healthCheckRules(),
            ...$this->attachmentRules(),
            'schedule_follow_up' => ['boolean'],
            'follow_up_reason' => [$skipFollowUp, 'required', 'string', 'max:255'],
            'follow_up_due_on' => [$skipFollowUp, 'required', 'date', 'after_or_equal:checked_on'],
            'follow_up_assigned_to' => [$skipFollowUp, 'nullable', 'integer', Rule::exists(User::class, 'id')],
        ];
    }

    /**
     * Get the custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->healthCheckMessages(),
            'follow_up_reason.required' => 'Enter the reason for the follow-up.',
            'follow_up_due_on.required' => 'Choose when the follow-up is due.',
            'follow_up_due_on.after_or_equal' => 'The follow-up cannot be due before the check date.',
        ];
    }
}
