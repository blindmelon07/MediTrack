<?php

namespace App\Policies;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\Employee;
use App\Models\User;

class AppointmentPolicy
{
    /**
     * Medical staff see the whole schedule, HR their company's bookings, employees their own.
     */
    public function viewAny(User $user): bool
    {
        return $user->managesHealthRecords()
            || ($user->hasRole(Role::Hr) && $user->company_id !== null)
            || $user->employee()->exists();
    }

    /**
     * Determine whether the user can book a clinic visit for the employee.
     */
    public function create(User $user, Employee $employee): bool
    {
        if (! $employee->is_active) {
            return false;
        }

        return $user->managesHealthRecords()
            || $user->isHrFor($employee->company_id)
            || $this->isOwn($user, $employee);
    }

    /**
     * Determine whether the user can cancel the appointment. Only upcoming, scheduled visits can be cancelled.
     */
    public function cancel(User $user, Appointment $appointment): bool
    {
        if ($appointment->status !== AppointmentStatus::Scheduled || ! $appointment->starts_at->isFuture()) {
            return false;
        }

        return $user->managesHealthRecords()
            || $user->isHrFor($appointment->employee->company_id)
            || $this->isOwn($user, $appointment->employee);
    }

    /**
     * Determine whether the user can mark a scheduled visit as completed or a no-show.
     */
    public function recordOutcome(User $user, Appointment $appointment): bool
    {
        return $user->managesHealthRecords() && $appointment->status === AppointmentStatus::Scheduled;
    }

    /**
     * Determine whether the employee record is the user's own.
     */
    private function isOwn(User $user, Employee $employee): bool
    {
        return $employee->user_id !== null && $employee->user_id === $user->id;
    }
}
