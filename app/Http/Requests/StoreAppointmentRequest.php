<?php

namespace App\Http\Requests;

use App\Enums\AppointmentPurpose;
use App\Enums\FollowUpStatus;
use App\Models\Appointment;
use App\Models\Employee;
use App\Models\FollowUp;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. A missing or unknown
     * employee is left to validation so the user sees a field error rather than a 403.
     */
    public function authorize(): bool
    {
        $employee = $this->employee();

        return $employee === null || $this->user()->can('create', [Appointment::class, $employee]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists(Employee::class, 'id')],
            'starts_at' => ['required', 'date_format:Y-m-d H:i'],
            'purpose' => ['required', Rule::enum(AppointmentPurpose::class)],
            'follow_up_id' => [
                'nullable',
                'integer',
                Rule::exists(FollowUp::class, 'id')
                    ->where('employee_id', $this->integer('employee_id'))
                    ->where('status', FollowUpStatus::Pending->value),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
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
            'employee_id.required' => 'Choose who the appointment is for.',
            'starts_at.required' => 'Choose a time slot.',
            'starts_at.date_format' => 'Choose a time slot.',
        ];
    }

    /**
     * Get the employee being booked, if the submitted ID matches one.
     */
    public function employee(): ?Employee
    {
        return $this->filled('employee_id') ? Employee::query()->find($this->integer('employee_id')) : null;
    }
}
