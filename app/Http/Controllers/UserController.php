<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * List user accounts.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $search = $request->string('search')->trim()->toString();

        $users = User::query()
            ->with(['company:id,name', 'employee:id,user_id,employee_number,first_name,last_name'])
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"),
            ))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => ['search' => $search],
            'roles' => Role::options(),
        ]);
    }

    /**
     * Show the form for creating an account.
     */
    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('users/create', $this->formOptions());
    }

    /**
     * Create an account. Accounts made by an administrator are trusted, so the email counts as verified.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $user = new User($request->safe()->only(['name', 'email', 'password']));
            $user->forceFill([
                'role' => $request->validated('role'),
                'company_id' => $request->validated('company_id'),
                'email_verified_at' => now(),
            ])->save();

            $this->linkEmployee($user, $request->validated('employee_id'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account created.')]);

        return to_route('users.index');
    }

    /**
     * Show the form for editing an account.
     */
    public function edit(User $user): Response
    {
        Gate::authorize('update', $user);

        return Inertia::render('users/edit', [
            'account' => [
                ...$user->only(['id', 'name', 'email', 'company_id']),
                'role' => $user->role->value,
                'employee_id' => $user->employee?->id,
            ],
            ...$this->formOptions($user),
        ]);
    }

    /**
     * Update an account. The password only changes when a new one is entered.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $user->fill($request->safe()->only(['name', 'email']));

            if ($request->filled('password')) {
                $user->password = $request->validated('password');
            }

            $user->forceFill([
                'role' => $request->validated('role'),
                'company_id' => $request->validated('company_id'),
            ])->save();

            $this->linkEmployee($user, $request->validated('employee_id'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account updated.')]);

        return to_route('users.index');
    }

    /**
     * Delete an account. The linked employee record and its health history are kept.
     */
    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account deleted.')]);

        return to_route('users.index');
    }

    /**
     * Point the account at a single employee record, detaching any record it was linked to before.
     */
    private function linkEmployee(User $user, ?int $employeeId): void
    {
        Employee::query()
            ->where('user_id', $user->id)
            ->when($employeeId !== null, fn (Builder $query) => $query->whereKeyNot($employeeId))
            ->update(['user_id' => null]);

        if ($employeeId !== null) {
            Employee::query()->whereKey($employeeId)->update(['user_id' => $user->id]);
        }
    }

    /**
     * Get the choices shared by the create and edit forms.
     *
     * @return array{roles: array<int, array{value: string, label: string}>, companies: Collection<int, Company>, employees: Collection<int, Employee>}
     */
    private function formOptions(?User $user = null): array
    {
        return [
            'roles' => Role::options(),
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::query()
                ->where(fn (Builder $query) => $query->whereNull('user_id')->when(
                    $user !== null,
                    fn (Builder $query) => $query->orWhere('user_id', $user->id),
                ))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'employee_number', 'first_name', 'last_name', 'email']),
        ];
    }
}
