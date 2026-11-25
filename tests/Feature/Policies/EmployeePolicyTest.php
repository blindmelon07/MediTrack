<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;

/**
 * Build the user for a role. "own employee" is the login linked to the employee under test;
 * "own HR" and "other HR" are HR staff of the employee's company and of a different company.
 */
function userForRole(string $role, Employee $employee): User
{
    return match ($role) {
        'admin' => User::factory()->admin()->create(),
        'medical staff' => User::factory()->medicalStaff()->create(),
        'own HR' => User::factory()->hr($employee->company)->create(),
        'other HR' => User::factory()->hr(Company::factory()->create())->create(),
        'HR without company' => User::factory()->create(['role' => 'hr', 'company_id' => null]),
        'own employee' => tap(User::factory()->create(), fn (User $user) => $employee->forceFill(['user_id' => $user->id])->save()),
        'other employee' => User::factory()->create(),
    };
}

test('grants employee abilities by role', function (string $role, array $expected) {
    $employee = Employee::factory()->create();
    $user = userForRole($role, $employee->fresh());

    $actual = [
        'viewAny' => $user->can('viewAny', Employee::class),
        'view' => $user->can('view', $employee->fresh()),
        'create' => $user->can('create', Employee::class),
        'update' => $user->can('update', $employee->fresh()),
        'viewMedicalRecords' => $user->can('viewMedicalRecords', $employee->fresh()),
        'manageMedicalRecords' => $user->can('manageMedicalRecords', $employee->fresh()),
    ];

    expect($actual)->toBe($expected);
})->with([
    'admin' => ['admin', ['viewAny' => true, 'view' => true, 'create' => true, 'update' => true, 'viewMedicalRecords' => true, 'manageMedicalRecords' => true]],
    'medical staff' => ['medical staff', ['viewAny' => true, 'view' => true, 'create' => true, 'update' => true, 'viewMedicalRecords' => true, 'manageMedicalRecords' => true]],
    'HR of the same company' => ['own HR', ['viewAny' => true, 'view' => true, 'create' => true, 'update' => true, 'viewMedicalRecords' => false, 'manageMedicalRecords' => false]],
    'HR of another company' => ['other HR', ['viewAny' => true, 'view' => false, 'create' => true, 'update' => false, 'viewMedicalRecords' => false, 'manageMedicalRecords' => false]],
    'HR with no company assigned' => ['HR without company', ['viewAny' => false, 'view' => false, 'create' => false, 'update' => false, 'viewMedicalRecords' => false, 'manageMedicalRecords' => false]],
    'the employee themselves' => ['own employee', ['viewAny' => false, 'view' => true, 'create' => false, 'update' => false, 'viewMedicalRecords' => true, 'manageMedicalRecords' => false]],
    'a different employee' => ['other employee', ['viewAny' => false, 'view' => false, 'create' => false, 'update' => false, 'viewMedicalRecords' => false, 'manageMedicalRecords' => false]],
]);
