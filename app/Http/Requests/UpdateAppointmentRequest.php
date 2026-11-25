<?php

namespace App\Http\Requests;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
{
    /**
     * Cancelling and recording an outcome are separate permissions. An unrecognized
     * status is left to validation.
     */
    public function authorize(): bool
    {
        /** @var Appointment $appointment */
        $appointment = $this->route('appointment');

        return match ($this->enum('status', AppointmentStatus::class)) {
            AppointmentStatus::Cancelled => $this->user()->can('cancel', $appointment),
            AppointmentStatus::Completed, AppointmentStatus::NoShow => $this->user()->can('recordOutcome', $appointment),
            AppointmentStatus::Scheduled => false,
            null => true,
        };
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AppointmentStatus::class)->except([AppointmentStatus::Scheduled])],
        ];
    }
}
