<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can list user accounts.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    /**
     * Determine whether the user can create user accounts.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    /**
     * Determine whether the user can change the account.
     */
    public function update(User $user, User $model): bool
    {
        return $user->hasRole(Role::Admin);
    }

    /**
     * Determine whether the user can delete the account. Admins cannot delete themselves.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->hasRole(Role::Admin) && $user->isNot($model);
    }
}
