<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class AuditLogPolicy
{
    /**
     * Only administrators may review who accessed medical records.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }
}
