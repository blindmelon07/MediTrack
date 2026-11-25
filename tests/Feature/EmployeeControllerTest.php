<?php

use App\Models\Employee;
use App\Models\FollowUp;
use App\Models\HealthCheck;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('employees.index'));

    $response->assertRedirect(route('login'));
});

describe('index', function () {
    test('lists only active employees by default', function () {
        $active = Employee::factory()->create();
        Employee::factory()->inactive()->create();

        $response = $this->actingAs(User::factory()->create())->get(route('employees.index'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('employees/index')
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $active->id));
    });

    test('lists inactive employees when filtered by inactive status', function () {
        Employee::factory()->create();
        $inactive = Employee::factory()->inactive()->create();

        $response = $this->actingAs(User::factory()->create())
            ->get(route('employees.index', ['status' => 'inactive']));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $inactive->id));
    });

    test('filters employees by name, employee number, or department', function (string $search) {
        $match = Employee::factory()->create([
            'first_name' => 'Maria',
            'last_name' => 'Reyes',
            'employee_number' => 'EMP-777',
            'department' => 'Warehouse',
        ]);
        Employee::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Cruz',
            'employee_number' => 'EMP-100',
            'department' => 'Finance',
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('employees.index', ['search' => $search]));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $match->id));
    })->with([
        'first name' => 'Maria',
        'last name' => 'Reyes',
        'employee number' => 'EMP-777',
        'department' => 'Warehouse',
    ]);

    test('shows each employee\'s latest health check and pending follow-up count', function () {
        $employee = Employee::factory()->create();
        HealthCheck::factory()->for($employee)->create(['checked_on' => '2026-01-10']);
        $latest = HealthCheck::factory()->for($employee)->create(['checked_on' => '2026-06-15']);
        FollowUp::factory()->for($employee)->count(2)->create();
        FollowUp::factory()->for($employee)->completed()->create();

        $response = $this->actingAs(User::factory()->create())->get(route('employees.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('employees.data.0.latest_health_check.id', $latest->id)
            ->where('employees.data.0.pending_follow_ups_count', 2));
    });
});

describe('store', function () {
    test('creates an employee and redirects to their profile', function () {
        $response = $this->actingAs(User::factory()->create())->post(route('employees.store'), [
            'employee_number' => 'EMP-001',
            'first_name' => 'Ana',
            'last_name' => 'Santos',
            'department' => 'Operations',
            'sex' => 'female',
            'is_active' => '1',
        ]);

        $employee = Employee::firstWhere('employee_number', 'EMP-001');
        expect($employee)->not->toBeNull()
            ->first_name->toBe('Ana')
            ->department->toBe('Operations')
            ->is_active->toBeTrue();
        $response->assertSessionHasNoErrors()->assertRedirect(route('employees.show', $employee));
    });

    test('requires an employee number and name', function () {
        $response = $this->actingAs(User::factory()->create())->post(route('employees.store'), []);

        $response->assertSessionHasErrors(['employee_number', 'first_name', 'last_name']);
        $this->assertDatabaseCount('employees', 0);
    });

    test('rejects a duplicate employee number', function () {
        Employee::factory()->create(['employee_number' => 'EMP-001']);

        $response = $this->actingAs(User::factory()->create())->post(route('employees.store'), [
            'employee_number' => 'EMP-001',
            'first_name' => 'Ana',
            'last_name' => 'Santos',
        ]);

        $response->assertSessionHasErrors(['employee_number' => 'The employee number has already been taken.']);
        $this->assertDatabaseCount('employees', 1);
    });
});

describe('show', function () {
    test('renders the employee with their health checks and follow-ups', function () {
        $employee = Employee::factory()->create();
        $healthCheck = HealthCheck::factory()->for($employee)->create();
        $followUp = FollowUp::factory()->for($employee)->create();
        HealthCheck::factory()->create();

        $response = $this->actingAs(User::factory()->create())->get(route('employees.show', $employee));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('employees/show')
            ->where('employee.id', $employee->id)
            ->has('healthChecks', 1)
            ->where('healthChecks.0.id', $healthCheck->id)
            ->has('followUps', 1)
            ->where('followUps.0.id', $followUp->id));
    });
});

describe('update', function () {
    test('updates the employee while keeping their own employee number', function () {
        $employee = Employee::factory()->create(['employee_number' => 'EMP-001']);

        $response = $this->actingAs(User::factory()->create())->put(route('employees.update', $employee), [
            'employee_number' => 'EMP-001',
            'first_name' => 'Ana',
            'last_name' => 'Santos',
            'is_active' => '0',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('employees.show', $employee));
        expect($employee->fresh())
            ->first_name->toBe('Ana')
            ->is_active->toBeFalse();
    });

    test('rejects another employee\'s employee number', function () {
        Employee::factory()->create(['employee_number' => 'EMP-002']);
        $employee = Employee::factory()->create(['employee_number' => 'EMP-001']);

        $response = $this->actingAs(User::factory()->create())->put(route('employees.update', $employee), [
            'employee_number' => 'EMP-002',
            'first_name' => 'Ana',
            'last_name' => 'Santos',
        ]);

        $response->assertSessionHasErrors('employee_number');
        expect($employee->fresh()->employee_number)->toBe('EMP-001');
    });
});
