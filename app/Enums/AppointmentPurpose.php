<?php

namespace App\Enums;

enum AppointmentPurpose: string
{
    case AnnualCheck = 'annual_check';
    case FollowUp = 'follow_up';
    case PreEmployment = 'pre_employment';
    case Consultation = 'consultation';
    case Other = 'other';

    /**
     * Get the human-readable label for the purpose.
     */
    public function label(): string
    {
        return match ($this) {
            self::AnnualCheck => 'Annual health check',
            self::FollowUp => 'Follow-up',
            self::PreEmployment => 'Pre-employment exam',
            self::Consultation => 'Consultation',
            self::Other => 'Other',
        };
    }

    /**
     * Get every purpose as a value and label pair for select inputs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $purpose): array => ['value' => $purpose->value, 'label' => $purpose->label()],
            self::cases(),
        );
    }
}
