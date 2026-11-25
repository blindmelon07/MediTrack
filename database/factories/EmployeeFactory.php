<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_number' => fake()->unique()->numerify('EMP-#####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'department' => fake()->randomElement(['Operations', 'Finance', 'Human Resources', 'IT', 'Sales']),
            'position' => fake()->jobTitle(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+639#########'),
            'birth_date' => fake()->dateTimeBetween('-60 years', '-20 years')->format('Y-m-d'),
            'sex' => fake()->randomElement(['male', 'female']),
            'hired_on' => fake()->dateTimeBetween('-10 years', '-1 month')->format('Y-m-d'),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the employee no longer works at the office.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
