<?php

use App\Enums\FollowUpStatus;
use App\Models\Employee;
use App\Models\FollowUp;
use App\Models\HealthCheck;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-25 09:00:00');
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('follow-ups.index'));

    $response->assertRedirect(route('login'));
});

describe('index', function () {
    test('lists pending follow-ups soonest due first by default', function () {
        $later = FollowUp::factory()->create(['due_on' => '2026-12-10']);
        $sooner = FollowUp::factory()->create(['due_on' => '2026-12-01']);
        FollowUp::factory()->completed()->create();

        $response = $this->actingAs(User::factory()->create())->get(route('follow-ups.index'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('follow-ups/index')
            ->where('filters.status', 'pending')
            ->has('followUps.data', 2)
            ->where('followUps.data.0.id', $sooner->id)
            ->where('followUps.data.1.id', $later->id));
    });

    test('lists only pending follow-ups past their due date when filtered by overdue', function () {
        $overdue = FollowUp::factory()->overdue()->create();
        FollowUp::factory()->create(['due_on' => '2026-11-25']);
        FollowUp::factory()->completed()->create(['due_on' => '2026-11-01']);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('follow-ups.index', ['status' => 'overdue']));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('followUps.data', 1)
            ->where('followUps.data.0.id', $overdue->id)
            ->where('followUps.data.0.is_overdue', true)
            ->where('overdueCount', 1));
    });

    test('rejects an unknown status filter', function () {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('follow-ups.index', ['status' => 'bogus']));

        $response->assertSessionHasErrors('status');
    });
});

describe('create', function () {
    test('prefills the employee from the health check it was opened from', function () {
        $healthCheck = HealthCheck::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->get(route('follow-ups.create', ['health_check_id' => $healthCheck->id]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('follow-ups/create')
            ->where('selectedEmployeeId', $healthCheck->employee_id)
            ->where('healthCheck.id', $healthCheck->id));
    });
});

describe('store', function () {
    test('schedules a pending follow-up and redirects to the employee', function () {
        $employee = Employee::factory()->create();
        $healthCheck = HealthCheck::factory()->for($employee)->create();

        $response = $this->actingAs(User::factory()->create())->post(route('follow-ups.store'), [
            'employee_id' => $employee->id,
            'health_check_id' => $healthCheck->id,
            'reason' => 'Review lipid panel',
            'due_on' => '2026-12-05',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('employees.show', $employee));
        expect(FollowUp::sole())
            ->health_check_id->toBe($healthCheck->id)
            ->reason->toBe('Review lipid panel')
            ->status->toBe(FollowUpStatus::Pending);
    });

    test('rejects a health check that belongs to another employee', function () {
        $employee = Employee::factory()->create();
        $otherEmployeesCheck = HealthCheck::factory()->create();

        $response = $this->actingAs(User::factory()->create())->post(route('follow-ups.store'), [
            'employee_id' => $employee->id,
            'health_check_id' => $otherEmployeesCheck->id,
            'reason' => 'Review lipid panel',
            'due_on' => '2026-12-05',
        ]);

        $response->assertSessionHasErrors([
            'health_check_id' => 'The selected health check does not belong to this employee.',
        ]);
        $this->assertDatabaseCount('follow_ups', 0);
    });

    test('requires the employee, reason, and due date', function () {
        $response = $this->actingAs(User::factory()->create())->post(route('follow-ups.store'), []);

        $response->assertSessionHasErrors(['employee_id', 'reason', 'due_on']);
    });
});

describe('update', function () {
    test('records today as the completion date when none is given', function () {
        $followUp = FollowUp::factory()->create();

        $response = $this->actingAs(User::factory()->create())->put(route('follow-ups.update', $followUp), [
            'employee_id' => $followUp->employee_id,
            'reason' => $followUp->reason,
            'due_on' => '2026-12-01',
            'status' => 'completed',
            'notes' => 'BP normal on recheck',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('employees.show', $followUp->employee_id));
        expect($followUp->fresh())
            ->status->toBe(FollowUpStatus::Completed)
            ->completed_on->toDateString()->toBe('2026-11-25')
            ->notes->toBe('BP normal on recheck');
    });

    test('keeps the completion date that was entered', function () {
        $followUp = FollowUp::factory()->create();

        $this->actingAs(User::factory()->create())->put(route('follow-ups.update', $followUp), [
            'employee_id' => $followUp->employee_id,
            'reason' => $followUp->reason,
            'due_on' => '2026-12-01',
            'status' => 'completed',
            'completed_on' => '2026-11-20',
        ]);

        expect($followUp->fresh()->completed_on->toDateString())->toBe('2026-11-20');
    });

    test('clears the completion date when reopened', function () {
        $followUp = FollowUp::factory()->completed()->create();

        $this->actingAs(User::factory()->create())->put(route('follow-ups.update', $followUp), [
            'employee_id' => $followUp->employee_id,
            'reason' => $followUp->reason,
            'due_on' => '2026-12-01',
            'status' => 'pending',
            'completed_on' => '2026-11-20',
        ]);

        expect($followUp->fresh())
            ->status->toBe(FollowUpStatus::Pending)
            ->completed_on->toBeNull();
    });
});

describe('destroy', function () {
    test('deletes the follow-up', function () {
        $followUp = FollowUp::factory()->create();

        $response = $this->actingAs(User::factory()->create())->delete(route('follow-ups.destroy', $followUp));

        $response->assertRedirect(route('employees.show', $followUp->employee_id));
        $this->assertModelMissing($followUp);
    });
});
