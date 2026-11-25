<?php

use App\Enums\FollowUpStatus;
use App\Enums\HealthCheckResult;
use App\Models\Employee;
use App\Models\FollowUp;
use App\Models\HealthCheck;
use App\Models\HealthCheckAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, mixed>
 */
function healthCheckPayload(Employee $employee, array $overrides = []): array
{
    return [
        'employee_id' => $employee->id,
        'type' => 'annual_physical',
        'checked_on' => '2026-11-20',
        'examined_by' => 'Dr. Santos',
        'blood_pressure' => '150/95',
        'heart_rate' => '88',
        'result' => 'abnormal',
        'diagnosis' => 'Hypertension',
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->travelTo('2026-11-25 09:00:00');
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('health-checks.index'));

    $response->assertRedirect(route('login'));
});

describe('index', function () {
    test('filters health checks by result', function () {
        $abnormal = HealthCheck::factory()->abnormal()->create();
        HealthCheck::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->get(route('health-checks.index', ['result' => 'abnormal']));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('health-checks/index')
            ->has('healthChecks.data', 1)
            ->where('healthChecks.data.0.id', $abnormal->id));
    });

    test('filters health checks by employee name', function () {
        $match = HealthCheck::factory()->for(Employee::factory()->state(['last_name' => 'Villanueva']))->create();
        HealthCheck::factory()->for(Employee::factory()->state(['last_name' => 'Cruz']))->create();

        $response = $this->actingAs(User::factory()->create())
            ->get(route('health-checks.index', ['search' => 'Villanueva']));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('healthChecks.data', 1)
            ->where('healthChecks.data.0.id', $match->id));
    });
});

describe('create', function () {
    test('preselects the employee given in the query string', function () {
        $employee = Employee::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->get(route('health-checks.create', ['employee_id' => $employee->id]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('health-checks/create')
            ->where('selectedEmployeeId', $employee->id));
    });

    test('offers active employees only', function () {
        $active = Employee::factory()->create();
        Employee::factory()->inactive()->create();

        $response = $this->actingAs(User::factory()->create())->get(route('health-checks.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('employees', 1)
            ->where('employees.0.id', $active->id));
    });
});

describe('store', function () {
    test('records the health check against the signed-in user', function () {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($user)->post(route('health-checks.store'), healthCheckPayload($employee));

        $healthCheck = HealthCheck::sole();
        expect($healthCheck)
            ->employee_id->toBe($employee->id)
            ->recorded_by->toBe($user->id)
            ->result->toBe(HealthCheckResult::Abnormal)
            ->blood_pressure->toBe('150/95');
        $response->assertSessionHasNoErrors()->assertRedirect(route('health-checks.show', $healthCheck));
        $this->assertDatabaseCount('follow_ups', 0);
    });

    test('schedules a pending follow-up when requested', function () {
        $nurse = User::factory()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs(User::factory()->create())->post(route('health-checks.store'), healthCheckPayload($employee, [
            'schedule_follow_up' => '1',
            'follow_up_reason' => 'Recheck blood pressure',
            'follow_up_due_on' => '2026-12-20',
            'follow_up_assigned_to' => $nurse->id,
        ]));

        $response->assertSessionHasNoErrors();
        expect(FollowUp::sole())
            ->health_check_id->toBe(HealthCheck::sole()->id)
            ->employee_id->toBe($employee->id)
            ->reason->toBe('Recheck blood pressure')
            ->due_on->toDateString()->toBe('2026-12-20')
            ->assigned_to->toBe($nurse->id)
            ->status->toBe(FollowUpStatus::Pending);
    });

    test('requires a reason and due date when scheduling a follow-up', function () {
        $employee = Employee::factory()->create();

        $response = $this->actingAs(User::factory()->create())->post(
            route('health-checks.store'),
            healthCheckPayload($employee, ['schedule_follow_up' => '1']),
        );

        $response->assertSessionHasErrors([
            'follow_up_reason' => 'Enter the reason for the follow-up.',
            'follow_up_due_on' => 'Choose when the follow-up is due.',
        ]);
        $this->assertDatabaseCount('health_checks', 0);
    });

    test('rejects a follow-up due before the check date', function () {
        $employee = Employee::factory()->create();

        $response = $this->actingAs(User::factory()->create())->post(route('health-checks.store'), healthCheckPayload($employee, [
            'schedule_follow_up' => '1',
            'follow_up_reason' => 'Recheck',
            'follow_up_due_on' => '2026-11-01',
        ]));

        $response->assertSessionHasErrors(['follow_up_due_on' => 'The follow-up cannot be due before the check date.']);
    });

    test('ignores follow-up fields when no follow-up is requested', function () {
        $employee = Employee::factory()->create();

        $response = $this->actingAs(User::factory()->create())->post(route('health-checks.store'), healthCheckPayload($employee, [
            'schedule_follow_up' => '0',
            'follow_up_due_on' => 'not-a-date',
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('health_checks', 1);
        $this->assertDatabaseCount('follow_ups', 0);
    });

    test('rejects a check dated in the future', function () {
        $employee = Employee::factory()->create();

        $response = $this->actingAs(User::factory()->create())->post(
            route('health-checks.store'),
            healthCheckPayload($employee, ['checked_on' => '2026-11-26']),
        );

        $response->assertSessionHasErrors(['checked_on' => 'The check date cannot be in the future.']);
    });

    test('rejects a malformed blood pressure reading', function () {
        $employee = Employee::factory()->create();

        $response = $this->actingAs(User::factory()->create())->post(
            route('health-checks.store'),
            healthCheckPayload($employee, ['blood_pressure' => '120-80']),
        );

        $response->assertSessionHasErrors([
            'blood_pressure' => 'Enter blood pressure as systolic/diastolic, for example 120/80.',
        ]);
    });

    test('requires the employee, type, date, and result', function () {
        $response = $this->actingAs(User::factory()->create())->post(route('health-checks.store'), []);

        $response->assertSessionHasErrors(['employee_id', 'type', 'checked_on', 'result']);
    });

    test('stores uploaded lab results on the private disk', function () {
        Storage::fake(HealthCheckAttachment::DISK);
        $employee = Employee::factory()->create();

        $response = $this->actingAs(User::factory()->create())->post(route('health-checks.store'), healthCheckPayload($employee, [
            'attachments' => [UploadedFile::fake()->create('cbc-results.pdf', 200, 'application/pdf')],
        ]));

        $response->assertSessionHasNoErrors();
        $attachment = HealthCheckAttachment::sole();
        expect($attachment)
            ->health_check_id->toBe(HealthCheck::sole()->id)
            ->original_name->toBe('cbc-results.pdf');
        Storage::disk(HealthCheckAttachment::DISK)->assertExists($attachment->path);
    });
});

describe('show', function () {
    test('renders the health check with its follow-ups and attachments', function () {
        $healthCheck = HealthCheck::factory()->create();
        $followUp = FollowUp::factory()->for($healthCheck->employee)->for($healthCheck)->create();

        $response = $this->actingAs(User::factory()->create())->get(route('health-checks.show', $healthCheck));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('health-checks/show')
            ->where('healthCheck.id', $healthCheck->id)
            ->where('healthCheck.employee.id', $healthCheck->employee_id)
            ->where('healthCheck.follow_ups.0.id', $followUp->id)
            ->has('healthCheck.attachments', 0));
    });
});

describe('update', function () {
    test('updates the health check', function () {
        $healthCheck = HealthCheck::factory()->create();

        $response = $this->actingAs(User::factory()->create())->put(
            route('health-checks.update', $healthCheck),
            healthCheckPayload($healthCheck->employee, ['result' => 'fit_with_restrictions', 'diagnosis' => 'Lower back strain']),
        );

        $response->assertSessionHasNoErrors()->assertRedirect(route('health-checks.show', $healthCheck));
        expect($healthCheck->fresh())
            ->result->toBe(HealthCheckResult::FitWithRestrictions)
            ->diagnosis->toBe('Lower back strain');
    });
});

describe('destroy', function () {
    test('deletes the health check and its files but keeps its follow-ups', function () {
        Storage::fake(HealthCheckAttachment::DISK);
        $healthCheck = HealthCheck::factory()->create();
        $attachment = $healthCheck->storeAttachment(UploadedFile::fake()->create('xray.pdf', 50, 'application/pdf'));
        $followUp = FollowUp::factory()->for($healthCheck->employee)->for($healthCheck)->create();

        $response = $this->actingAs(User::factory()->create())->delete(route('health-checks.destroy', $healthCheck));

        $response->assertRedirect(route('employees.show', $healthCheck->employee_id));
        $this->assertModelMissing($healthCheck);
        $this->assertModelMissing($attachment);
        Storage::disk(HealthCheckAttachment::DISK)->assertMissing($attachment->path);
        expect($followUp->fresh()->health_check_id)->toBeNull();
    });
});
