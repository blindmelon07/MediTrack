<?php

namespace App\Policies;

use App\Models\FollowUp;
use App\Models\User;

class FollowUpPolicy
{
    /**
     * Determine whether the user can list every follow-up.
     */
    public function viewAny(User $user): bool
    {
        return $user->managesHealthRecords();
    }

    /**
     * Determine whether the user can schedule follow-ups.
     */
    public function create(User $user): bool
    {
        return $user->managesHealthRecords();
    }

    /**
     * Determine whether the user can update the follow-up or record its outcome.
     */
    public function update(User $user, FollowUp $followUp): bool
    {
        return $user->managesHealthRecords();
    }

    /**
     * Determine whether the user can delete the follow-up.
     */
    public function delete(User $user, FollowUp $followUp): bool
    {
        return $user->managesHealthRecords();
    }
}
