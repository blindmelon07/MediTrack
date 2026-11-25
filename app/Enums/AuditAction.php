<?php

namespace App\Enums;

enum AuditAction: string
{
    case Viewed = 'viewed';
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Downloaded = 'downloaded';
    case Imported = 'imported';

    /**
     * Get the human-readable label for the action.
     */
    public function label(): string
    {
        return match ($this) {
            self::Viewed => 'Viewed',
            self::Created => 'Created',
            self::Updated => 'Updated',
            self::Deleted => 'Deleted',
            self::Downloaded => 'Downloaded',
            self::Imported => 'Imported',
        };
    }

    /**
     * Get every action as a value and label pair for select inputs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $action): array => ['value' => $action->value, 'label' => $action->label()],
            self::cases(),
        );
    }
}
