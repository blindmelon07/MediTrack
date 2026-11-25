<?php

namespace App\Enums;

enum HealthCheckType: string
{
    case AnnualPhysical = 'annual_physical';
    case PreEmployment = 'pre_employment';
    case LabTest = 'lab_test';
    case Vitals = 'vitals';
    case FitnessToWork = 'fitness_to_work';
    case Other = 'other';

    /**
     * Get the human-readable label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::AnnualPhysical => 'Annual physical',
            self::PreEmployment => 'Pre-employment exam',
            self::LabTest => 'Lab test',
            self::Vitals => 'Vitals check',
            self::FitnessToWork => 'Fitness-to-work',
            self::Other => 'Other',
        };
    }

    /**
     * Get every type as a value and label pair for select inputs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
