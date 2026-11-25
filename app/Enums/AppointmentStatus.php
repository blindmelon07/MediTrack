<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Completed => 'Completed',
            self::NoShow => 'No-show',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Get every status as a value and label pair for select inputs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }
}
