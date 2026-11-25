<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case MedicalStaff = 'medical_staff';
    case Hr = 'hr';
    case Employee = 'employee';

    /**
     * Get the human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::MedicalStaff => 'Medical staff',
            self::Hr => 'HR',
            self::Employee => 'Employee',
        };
    }

    /**
     * Determine whether the role may see and change medical records of every employee.
     */
    public function managesHealthRecords(): bool
    {
        return in_array($this, [self::Admin, self::MedicalStaff], true);
    }

    /**
     * Get every role as a value and label pair for select inputs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $role): array => ['value' => $role->value, 'label' => $role->label()],
            self::cases(),
        );
    }
}
