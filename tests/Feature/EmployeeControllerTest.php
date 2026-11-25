<?php

use App\Models\Company;
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

        $response = $this->actingAs(User::factory()->medicalStaff()->create())->get(route('employees.index'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('employees/index')
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $active->id));
    });

    test('lists inactive employees when filtered by inactive status', function () {
        Employee::factory()->create();
        $inactive = Employee::factory()->inactive()->create();

        $response = $this->actingAs(User::factory()->medicalStaff()->create())
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

        $response = $this->actingAs(User::factory()->medicalStaff()->create())
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

    test('filters employees by company for medical staff', function () {
        $gghi = Company::firstWhere('name', 'GGHI');
        $match = Employee::factory()->for($gghi)->create();
        Employee::factory()->for(Company::firstWhere('name', 'Tripple2'))->create();

        $response = $this->actingAs(User::factory()->medicalStaff()->create())
            ->get(route('employees.index', ['company_id' => $gghi->id]));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $match->id)
            ->where('employees.data.0.company', 'GGHI'));
    });

    test('shows the latest check result and follow-up counts to medical staff', function () {
        $this->travelTo('2026-11-25');
        $employee = Employee::factory()->create();
        HealthCheck::factory()->for($employee)->create(['checked_on' => '2026-01-10']);
        HealthCheck::factory()->for($employee)->abnormal()->create(['checked_on' => '2026-06-15']);
        FollowUp::factory()->for($employee)->count(2)->create();
        FollowUp::factory()->for($employee)->overdue()->create();
        FollowUp::factory()->for($employee)->completed()->create();

        $response = $this->actingAs(User::factory()->medicalStaff()->create())->get(route('employees.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('employees.data.0.latest_check.checked_on', '2026-06-15')
            ->where('employees.data.0.latest_check.result', 'abnormal')
            ->where('employees.data.0.latest_check.clearance.value', 'needs_follow_up')
            ->where('employees.data.0.pending_follow_ups_count', 3)
            ->where('employees.data.0.overdue_follow_ups_count', 1));
    });

    test('lists only the HR user\'s own company, without medical results', function () {
        $gghi = Company::firstWhere('name', 'GGHI');
        $ownEmployee = Employee::factory()->for($gghi)->create();
        HealthCheck::factory()->for($ownEmployee)->abnormal()->create();
        Employee::factory()->for(Company::firstWhere('name', 'GSAC Main'))->create();

        $response = $this->actingAs(User::factory()->hr($gghi)->create())->get(route('employees.index'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $ownEmployee->id)
            ->where('employees.data.0.latest_check.result', null)
            ->where('employees.data.0.latest_check.clearance.label', 'Needs follow-up')
            ->where('companies', []));
    });

    test('filters employees whose annual check is due and shows where each stands', function () {
        $this->travelTo('2026-11-25');
        $due = Employee::factory()->create();
        HealthCheck::factory()->for($due)->create(['type' => 'annual_physical', 'checked_on' => '2025-12-01']);
        $upToDate = Employee::factory()->create();
        HealthCheck::factory()->for($upToDate)->create(['type' => 'annual_physical', 'checked_on' => '2026-08-01']);

        $response = $this->actingAs(User::factory()->medicalStaff()->create())
            ->get(route('employees.index', ['annual' => 'due']));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $due->id)
            ->where('employees.data.0.annual_check.status', 'due_soon')
            ->where('employees.data.0.annual_check.due_on', '2026-12-01'));
    });

    test('forbids employees from listing employees', function () {
        $response = $this->actingAs(User::factory()->create())->get(route('employees.index'));

        $response->assertForbidden();
    });
});

describe('store', function () {
    test('creates an employee and redirects to their profile', function () {
        $company = Company::firstWhere('name', 'GSAC Main');

        $response = $this->actingAs(User::factory()->medicalStaff()->create())->post(route('employees.store'), [
            'company_id' => $company->id,
            'employee_number' => 'EMP-001',
            'first_name' => 'Ana',
            'last_name' => 'Santos',
            'department' => 'Operations',
            'sex' => 'female',
            'is_active' => '1',
        ]);

        $employee = Employee::firstWhere('employee_number', 'EMP-001');
        expect($employee)->not->toBeNull()
            ->company_id->toBe($company->id)
            ->first_name->toBe('Ana')
            ->department->toBe('Operations')
            ->is_active->toBeTrue();
        $response->assertSessionHasNoErrors()->assertRedirect(route('employees.show', $employee));
    });

    test('requires a company, employee number, and name', function () {
        $response = $this->actingAs(User::factory()->medicalStaff()->create())->post(route('employees.store'), []);

        $response->assertSessionHasErrors([
            'company_id' => 'Choose the company the employee works for.',
            'employee_number',
            'first_name',
            'last_name',
        ]);
        $this->assertDatabaseCount('employees', 0);
    });

    test('rejects a duplicate employee number', function () {
        Employee::factory()->create(['employee_number' => 'EMP-001']);

        $response = $this->actingAs(User::factory()->medicalStaff()->create())->post(route('employees.store'), [
            'company_id' => Company::value('id'),
            'employee_number' => 'EMP-001',
            'first_name' => 'Ana',
            'last_name' => 'Santos',
        ]);

        $response->assertSessionHasErrors(['employee_number' => 'The employee number has already been taken.']);
        $this->assertDatabaseCount('employees', 1);
    });

    test('files employees added by HR under the HR user\'s own company', function () {
        $tripple2 = Company::firstWhere('name', 'Tripple2');

        $response = $this->actingAs(User::factory()->hr($tripple2)->create())->post(route('employees.store'), [
            'company_id' => Company::firstWhere('name', 'GGHI')->id,
            'employee_number' => 'T2-001',
            'first_name' => 'Ana',
            'last_name' => 'Santos',
        ]);

        $response->assertSessionHasNoErrors();
        expect(Employee::sole()->company_id)->toBe($tripple2->id);
    });

    test('stores the mobile number in the form SMS reminders need', function () {
        $this->actingAs(User::factory()->medicalStaff()->create())->post(route('employees.store'), [
            'company_id' => Company::value('id'),
            'employee_number' => 'EMP-001',
            'first_name' => 'Ana',
            'last_name' => 'Santos',
            'phone' => '0917 123 4567',
        ]);

        expect(Employee::sole()->phone)->toBe('+639171234567');
    });

    test('rejects a phone number that cannot receive SMS', function () {
        $response = $this->actingAs(User::factory()->medicalStaff()->create())->post(route('employees.store'), [
            'company_id' => Company::value('id'),
            'employee_number' => 'EMP-001',
            'first_name' => 'Ana',
            'last_name' => 'Santos',
            'phone' => '(02) 8123 4567',
        ]);

        $response->assertSessionHasErrors(['phone' => 'Enter a Philippine mobile number, for example 0917 123 4567.']);
        $this->assertDatabaseCount('employees', 0);
    });

    test('forbids employees from adding employees', function () {
        $response = $this->actingAs(User::factory()->create())->post(route('employees.store'), [
            'company_id' => Company::value('id'),
            'employee_number' => 'EMP-001',
            'first_name' => 'Ana',
            'last_name' => 'Santos',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('employees', 0);
    });
});

describe('show', function () {
    test('renders the full health history for medical staff', function () {
        $employee = Employee::factory()->create();
        $healthCheck = HealthCheck::factory()->for($employee)->abnormal()->create();
        $followUp = FollowUp::factory()->for($employee)->create(['reason' => 'Recheck blood pressure']);
        HealthCheck::factory()->create();

        $response = $this->actingAs(User::factory()->medicalStaff()->create())->get(route('employees.show', $employee));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('employees/show')
            ->where('employee.id', $employee->id)
            ->where('canViewMedicalRecords', true)
            ->where('can.manageMedicalRecords', true)
            ->has('healthChecks', 1)
            ->where('healthChecks.0.id', $healthCheck->id)
            ->where('healthChecks.0.diagnosis', 'Elevated blood pressure')
            ->has('followUps', 1)
            ->where('followUps.0.reason', 'Recheck blood pressure'));
    });

    test('shows HR only clearance and follow-up dates, never diagnoses or reasons', function () {
        $company = Company::firstWhere('name', 'GGHI');
        $employee = Employee::factory()->for($company)->create();
        HealthCheck::factory()->for($employee)->abnormal()->create(['checked_on' => '2026-06-15']);
        FollowUp::factory()->for($employee)->create(['reason' => 'Recheck blood pressure', 'due_on' => '2026-12-01']);

        $response = $this->actingAs(User::factory()->hr($company)->create())->get(route('employees.show', $employee));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('canViewMedicalRecords', false)
            ->where('can.update', true)
            ->where('can.manageMedicalRecords', false)
            ->has('healthChecks.0', fn (Assert $check) => $check
                ->where('checked_on', '2026-06-15')
                ->where('clearance.label', 'Needs follow-up')
                ->etc()
                ->missing('diagnosis')
                ->missing('result')
                ->missing('blood_pressure'))
            ->has('followUps.0', fn (Assert $followUp) => $followUp
                ->where('due_on', '2026-12-01')
                ->etc()
                ->missing('reason')
                ->missing('notes')));
        $response->assertDontSee('Elevated blood pressure')->assertDontSee('Recheck blood pressure');
    });

    test('forbids HR from viewing another company\'s employee', function () {
        $employee = Employee::factory()->for(Company::firstWhere('name', 'GSAC Main'))->create();

        $response = $this->actingAs(User::factory()->hr(Company::firstWhere('name', 'GGHI'))->create())
            ->get(route('employees.show', $employee));

        $response->assertForbidden();
    });

    test('lets an employee view their own full health record but not change it', function () {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        HealthCheck::factory()->for($employee)->abnormal()->create();

        $response = $this->actingAs($user)->get(route('employees.show', $employee));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('canViewMedicalRecords', true)
            ->where('can.update', false)
            ->where('can.manageMedicalRecords', false)
            ->where('healthChecks.0.diagnosis', 'Elevated blood pressure'));
    });

    test('forbids an employee from viewing a coworker\'s record', function () {
        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);
        $coworker = Employee::factory()->create();

        $response = $this->actingAs($user)->get(route('employees.show', $coworker));

        $response->assertForbidden();
    });
});

describe('update', function () {
    test('updates the employee while keeping their own employee number', function () {
        $employee = Employee::factory()->create(['employee_number' => 'EMP-001']);

        $response = $this->actingAs(User::factory()->medicalStaff()->create())->put(route('employees.update', $employee), [
            'company_id' => $employee->company_id,
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

        $response = $this->actingAs(User::factory()->medicalStaff()->create())->put(route('employees.update', $employee), [
            'company_id' => $employee->company_id,
            'employee_number' => 'EMP-002',
            'first_name' => 'Ana',
            'last_name' => 'Santos',
        ]);

        $response->assertSessionHasErrors('employee_number');
        expect($employee->fresh()->employee_number)->toBe('EMP-001');
    });

    test('forbids HR from editing another company\'s employee', function () {
        $employee = Employee::factory()->for(Company::firstWhere('name', 'GSAC Main'))->create(['first_name' => 'Original']);

        $response = $this->actingAs(User::factory()->hr(Company::firstWhere('name', 'GGHI'))->create())
            ->put(route('employees.update', $employee), [
                'employee_number' => $employee->employee_number,
                'first_name' => 'Changed',
                'last_name' => 'Santos',
            ]);

        $response->assertForbidden();
        expect($employee->fresh()->first_name)->toBe('Original');
    });
});
