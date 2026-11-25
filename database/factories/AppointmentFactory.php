<?php

namespace Database\Factories;

use App\Enums\AppointmentPurpose;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'follow_up_id' => null,
            'starts_at' => today()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s'),
            'purpose' => AppointmentPurpose::AnnualCheck,
            'status' => AppointmentStatus::Scheduled,
            'notes' => null,
        ];
    }

    /**
     * Book the appointment for a specific time.
     */
    public function at(string $startsAt): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => $startsAt,
        ]);
    }

    /**
     * Indicate that the appointment was cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Cancelled,
        ]);
    }
}
