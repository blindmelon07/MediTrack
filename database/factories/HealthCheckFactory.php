<?php

namespace Database\Factories;

use App\Enums\HealthCheckResult;
use App\Enums\HealthCheckType;
use App\Models\Employee;
use App\Models\HealthCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthCheck>
 */
class HealthCheckFactory extends Factory
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
            'type' => HealthCheckType::AnnualPhysical,
            'checked_on' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'examined_by' => 'Dr. '.fake()->lastName(),
            'blood_pressure' => fake()->numberBetween(100, 140).'/'.fake()->numberBetween(60, 90),
            'heart_rate' => fake()->numberBetween(60, 100),
            'temperature' => fake()->randomFloat(1, 36.1, 37.2),
            'weight_kg' => fake()->randomFloat(1, 50, 95),
            'height_cm' => fake()->randomFloat(1, 150, 185),
            'findings' => fake()->sentence(),
            'diagnosis' => null,
            'recommendations' => null,
            'result' => HealthCheckResult::Normal,
        ];
    }

    /**
     * Indicate that the health check found an abnormal result.
     */
    public function abnormal(): static
    {
        return $this->state(fn (array $attributes) => [
            'result' => HealthCheckResult::Abnormal,
            'diagnosis' => 'Elevated blood pressure',
            'recommendations' => 'Recheck in one month',
        ]);
    }
}
