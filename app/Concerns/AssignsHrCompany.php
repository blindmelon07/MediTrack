<?php

namespace App\Concerns;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

/**
 * For employee form requests: pins HR users to their own company so they cannot
 * file employees under another one.
 *
 * @mixin FormRequest
 */
trait AssignsHrCompany
{
    /**
     * Replace the submitted company with the HR user's own company.
     */
    protected function assignHrCompany(): void
    {
        $user = $this->user();

        if ($user !== null && $user->hasRole(Role::Hr)) {
            $this->merge(['company_id' => $user->company_id]);
        }
    }
}
