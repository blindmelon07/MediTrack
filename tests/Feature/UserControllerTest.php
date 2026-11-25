<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('public self-registration is closed', function () {
    $response = $this->post('/register', [
        'name' => 'Stranger',
        'email' => 'stranger@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertNotFound();
    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
});

test('forbids non-administrators from managing accounts', function (string $role) {
    $response = $this->actingAs(User::factory()->create(['role' => $role]))->get(route('users.index'));

    $response->assertForbidden();
})->with(['medical staff' => 'medical_staff', 'HR' => 'hr', 'employee' => 'employee']);

test('shares only the pages a user may open with the interface', function () {
    $company = Company::factory()->create();
    $user = User::factory()->hr($company)->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('auth.can.viewEmployees', true)
        ->where('auth.can.manageHealthRecords', false)
        ->where('auth.can.manageUsers', false)
        ->where('auth.can.ownEmployeeId', null));
});

describe('index', function () {
    test('lists accounts for administrators', function () {
        $admin = User::factory()->admin()->create();
        User::factory()->medicalStaff()->create();

        $response = $this->actingAs($admin)->get(route('users.index'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('users/index')
            ->has('users.data', 2));
    });
});

describe('store', function () {
    test('creates a verified HR account for one company', function () {
        $company = Company::firstWhere('name', 'GGHI');

        $response = $this->actingAs(User::factory()->admin()->create())->post(route('users.store'), [
            'name' => 'GGHI HR',
            'email' => 'hr@gghi.example',
            'role' => 'hr',
            'company_id' => $company->id,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('users.index'));
        expect(User::firstWhere('email', 'hr@gghi.example'))
            ->role->toBe(Role::Hr)
            ->company_id->toBe($company->id)
            ->email_verified_at->not->toBeNull();
    });

    test('requires a company for HR accounts', function () {
        $response = $this->actingAs(User::factory()->admin()->create())->post(route('users.store'), [
            'name' => 'HR',
            'email' => 'hr@example.com',
            'role' => 'hr',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors(['company_id' => 'Choose which company this HR account manages.']);
        $this->assertDatabaseMissing('users', ['email' => 'hr@example.com']);
    });

    test('ignores a company for roles that see every company', function () {
        $this->actingAs(User::factory()->admin()->create())->post(route('users.store'), [
            'name' => 'Nurse',
            'email' => 'nurse@example.com',
            'role' => 'medical_staff',
            'company_id' => Company::value('id'),
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        expect(User::firstWhere('email', 'nurse@example.com')->company_id)->toBeNull();
    });

    test('links an employee account to its health record', function () {
        $employee = Employee::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->post(route('users.store'), [
            'name' => 'Ana Santos',
            'email' => 'ana@example.com',
            'role' => 'employee',
            'employee_id' => $employee->id,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        expect($employee->fresh()->user_id)->toBe(User::firstWhere('email', 'ana@example.com')->id);
    });

    test('rejects linking an employee record that already has an account', function () {
        $employee = Employee::factory()->create(['user_id' => User::factory()->create()->id]);

        $response = $this->actingAs(User::factory()->admin()->create())->post(route('users.store'), [
            'name' => 'Impostor',
            'email' => 'impostor@example.com',
            'role' => 'employee',
            'employee_id' => $employee->id,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors(['employee_id' => 'That employee record is already linked to another account.']);
        $this->assertDatabaseMissing('users', ['email' => 'impostor@example.com']);
    });

    test('forbids medical staff from creating accounts', function () {
        $response = $this->actingAs(User::factory()->medicalStaff()->create())->post(route('users.store'), [
            'name' => 'Sneaky Admin',
            'email' => 'sneaky@example.com',
            'role' => 'admin',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    });
});

describe('update', function () {
    test('keeps the current password when none is entered', function () {
        $account = User::factory()->medicalStaff()->create(['password' => 'original-password']);

        $response = $this->actingAs(User::factory()->admin()->create())->put(route('users.update', $account), [
            'name' => 'Renamed Nurse',
            'email' => $account->email,
            'role' => 'medical_staff',
        ]);

        $response->assertSessionHasNoErrors();
        $account->refresh();
        expect($account->name)->toBe('Renamed Nurse');
        expect(Hash::check('original-password', $account->password))->toBeTrue();
    });

    test('clears the company when HR becomes medical staff', function () {
        $account = User::factory()->hr(Company::factory()->create())->create();

        $this->actingAs(User::factory()->admin()->create())->put(route('users.update', $account), [
            'name' => $account->name,
            'email' => $account->email,
            'role' => 'medical_staff',
        ]);

        expect($account->fresh())
            ->role->toBe(Role::MedicalStaff)
            ->company_id->toBeNull();
    });

    test('moves the employee link when a different record is chosen', function () {
        $account = User::factory()->create();
        $previous = Employee::factory()->create(['user_id' => $account->id]);
        $next = Employee::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->put(route('users.update', $account), [
            'name' => $account->name,
            'email' => $account->email,
            'role' => 'employee',
            'employee_id' => $next->id,
        ]);

        expect($previous->fresh()->user_id)->toBeNull();
        expect($next->fresh()->user_id)->toBe($account->id);
    });

    test('stops administrators from removing their own admin access', function () {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'employee',
        ]);

        $response->assertSessionHasErrors(['role' => 'You cannot remove your own administrator access.']);
        expect($admin->fresh()->role)->toBe(Role::Admin);
    });
});

describe('destroy', function () {
    test('deletes the account but keeps the employee health record', function () {
        $account = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $account->id]);

        $response = $this->actingAs(User::factory()->admin()->create())->delete(route('users.destroy', $account));

        $response->assertRedirect(route('users.index'));
        $this->assertModelMissing($account);
        expect($employee->fresh())->not->toBeNull()->user_id->toBeNull();
    });

    test('stops administrators from deleting their own account here', function () {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->delete(route('users.destroy', $admin));

        $response->assertForbidden();
        $this->assertModelExists($admin);
    });
});
