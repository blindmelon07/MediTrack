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
     * Get the work-clearance status HR may see in place of the medical result.
     *
     * @return array{value: string, label: string}
     */
    public function clearance(): array
    {
        return match ($this) {
            self::Normal, self::Fit => ['value' => 'cleared', 'label' => 'Cleared'],
            self::FitWithRestrictions => ['value' => 'restricted', 'label' => 'Cleared with restrictions'],
            self::Unfit => ['value' => 'not_cleared', 'label' => 'Not cleared'],
            self::Abnormal => ['value' => 'needs_follow_up', 'label' => 'Needs follow-up'],
            self::Pending => ['value' => 'pending', 'label' => 'Awaiting results'],
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
