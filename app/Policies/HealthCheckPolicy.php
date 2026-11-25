<?php

namespace App\Policies;

use App\Models\HealthCheck;
use App\Models\User;

class HealthCheckPolicy
{
    /**
     * Determine whether the user can list every health check.
     */
    public function viewAny(User $user): bool
    {
        return $user->managesHealthRecords();
    }

    /**
     * Determine whether the user can view the health check and download its files.
     */
    public function view(User $user, HealthCheck $healthCheck): bool
    {
        return $user->managesHealthRecords()
            || ($healthCheck->employee->user_id !== null && $healthCheck->employee->user_id === $user->id);
    }

    /**
     * Determine whether the user can record health checks.
     */
    public function create(User $user): bool
    {
        return $user->managesHealthRecords();
    }

    /**
     * Determine whether the user can change the health check or its files.
     */
    public function update(User $user, HealthCheck $healthCheck): bool
    {
        return $user->managesHealthRecords();
    }

    /**
     * Determine whether the user can delete the health check.
     */
    public function delete(User $user, HealthCheck $healthCheck): bool
    {
        return $user->managesHealthRecords();
    }
}
