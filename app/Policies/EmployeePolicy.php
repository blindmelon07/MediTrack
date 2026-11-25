<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    /**
     * Determine whether the user can list employees.
     */
    public function viewAny(User $user): bool
    {
        return $user->managesHealthRecords() || $this->isHrWithCompany($user);
    }

    /**
     * Determine whether the user can view the employee's profile.
     */
    public function view(User $user, Employee $employee): bool
    {
        return $user->managesHealthRecords()
            || $user->isHrFor($employee->company_id)
            || $this->isOwnRecord($user, $employee);
    }

    /**
     * Determine whether the user can add employees.
     */
    public function create(User $user): bool
    {
        return $user->managesHealthRecords() || $this->isHrWithCompany($user);
    }

    /**
     * Determine whether the user can change the employee's details.
     */
    public function update(User $user, Employee $employee): bool
    {
        return $user->managesHealthRecords() || $user->isHrFor($employee->company_id);
    }

    /**
     * Determine whether the user can see the employee's diagnoses, vitals, and lab files.
     * HR only sees clearance and follow-up due dates.
     */
    public function viewMedicalRecords(User $user, Employee $employee): bool
    {
        return $user->managesHealthRecords() || $this->isOwnRecord($user, $employee);
    }

    /**
     * Determine whether the user can record health checks and follow-ups for the employee.
     */
    public function manageMedicalRecords(User $user, Employee $employee): bool
    {
        return $user->managesHealthRecords();
    }

    /**
     * Determine whether the employee record belongs to the signed-in user.
     */
    private function isOwnRecord(User $user, Employee $employee): bool
    {
        return $employee->user_id !== null && $employee->user_id === $user->id;
    }

    /**
     * Determine whether the user is HR staff assigned to a company.
     */
    private function isHrWithCompany(User $user): bool
    {
        return $user->hasRole(Role::Hr) && $user->company_id !== null;
    }
}
