<?php

namespace App\Enums;

enum HealthCheckResult: string
{
    case Normal = 'normal';
    case Abnormal = 'abnormal';
    case Fit = 'fit';
    case FitWithRestrictions = 'fit_with_restrictions';
    case Unfit = 'unfit';
    case Pending = 'pending';

    /**
     * Get the human-readable label for the result.
     */
    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Abnormal => 'Abnormal',
            self::Fit => 'Fit to work',
            self::FitWithRestrictions => 'Fit with restrictions',
            self::Unfit => 'Unfit to work',
            self::Pending => 'Pending results',
        };
    }

    /**
     * Determine whether the result calls for a follow-up.
     */
    public function needsFollowUp(): bool
    {
        return in_array($this, [self::Abnormal, self::FitWithRestrictions, self::Unfit], true);
    }

    /**
     * Get every result as a value and label pair for select inputs.
     *
     * @return array<int, array{value: string, label: string, needsFollowUp: bool}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $result): array => [
                'value' => $result->value,
                'label' => $result->label(),
                'needsFollowUp' => $result->needsFollowUp(),
            ],
            self::cases(),
        );
    }
}
