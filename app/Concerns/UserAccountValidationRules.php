<?php

namespace App\Concerns;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait UserAccountValidationRules
{
    /**
     * Get the validation rules for accounts that administrators create and edit.
     *
     * @return array<string, array<int, ValidationRule|string|object>>
     */
    protected function userAccountRules(?User $user = null): array
    {
        $role = $this->enum('role', Role::class);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                $user === null ? Rule::unique(User::class) : Rule::unique(User::class)->ignore($user->id),
            ],
            'role' => ['required', Rule::enum(Role::class)],
            'company_id' => [
                Rule::excludeIf($role !== Role::Hr),
                'required',
                'integer',
                Rule::exists(Company::class, 'id'),
            ],
            'employee_id' => [
                Rule::excludeIf($role !== Role::Employee),
                'nullable',
                'integer',
                Rule::exists(Employee::class, 'id')->where(
                    fn (Builder $query) => $query->whereNull('user_id')->when(
                        $user !== null,
                        fn (Builder $query) => $query->orWhere('user_id', $user->id),
                    ),
                ),
            ],
            'password' => [$user === null ? 'required' : 'nullable', 'string', Password::default(), 'confirmed'],
        ];
    }

    /**
     * Get the custom messages for account validation.
     *
     * @return array<string, string>
     */
    protected function userAccountMessages(): array
    {
        return [
            'company_id.required' => 'Choose which company this HR account manages.',
            'employee_id.exists' => 'That employee record is already linked to another account.',
        ];
    }
}
