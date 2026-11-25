<?php

namespace Database\Factories;

use App\Enums\FollowUpStatus;
use App\Models\Employee;
use App\Models\FollowUp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FollowUp>
 */
class FollowUpFactory extends Factory
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
            'health_check_id' => null,
            'assigned_to' => null,
            'reason' => fake()->sentence(4),
            'due_on' => today()->addDays(fake()->numberBetween(1, 30))->format('Y-m-d'),
            'status' => FollowUpStatus::Pending,
            'completed_on' => null,
            'notes' => null,
        ];
    }

    /**
     * Indicate that the follow-up is pending and past its due date.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FollowUpStatus::Pending,
            'due_on' => today()->subDays(3)->format('Y-m-d'),
        ]);
    }

    /**
     * Indicate that the follow-up was completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FollowUpStatus::Completed,
            'completed_on' => today()->format('Y-m-d'),
        ]);
    }
}
