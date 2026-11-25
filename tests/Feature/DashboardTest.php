<?php

use App\Enums\HealthCheckType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FollowUp;
use App\Models\HealthCheck;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-25 09:00:00');
    config([
        'meditrack.annual_check.interval_months' => 12,
        'meditrack.annual_check.reminder_days' => 30,
        'meditrack.follow_up_lookahead_days' => 7,
    ]);
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

describe('annual check schedule', function () {
    test('classifies an employee by the date of their last full exam', function (?string $lastExamOn, string $status, ?string $dueOn) {
        $employee = Employee::factory()->create();
        if ($lastExamOn !== null) {
            HealthCheck::factory()->for($employee)->create(['type' => HealthCheckType::AnnualPhysical, 'checked_on' => $lastExamOn]);
        }

        $summary = Employee::query()->withLastAnnualCheck()->find($employee->id)->annualCheckSummary();

        expect($summary)->toBe(['status' => $status, 'last_checked_on' => $lastExamOn, 'due_on' => $dueOn]);
    })->with([
        'never examined' => [null, 'never', null],
        'examined over a year ago' => ['2025-11-24', 'overdue', '2026-11-24'],
        'due today' => ['2025-11-25', 'due_soon', '2026-11-25'],
        'due on the last day of the reminder window' => ['2025-12-25', 'due_soon', '2026-12-25'],
        'due the day after the reminder window' => ['2025-12-26', 'up_to_date', '2026-12-26'],
        'examined recently' => ['2026-09-01', 'up_to_date', '2027-09-01'],
    ]);

    test('agrees with the summary about who is overdue and who is due soon', function () {
        $overdue = Employee::factory()->create();
        HealthCheck::factory()->for($overdue)->create(['type' => HealthCheckType::AnnualPhysical, 'checked_on' => '2025-11-24']);
        $dueToday = Employee::factory()->create();
        HealthCheck::factory()->for($dueToday)->create(['type' => HealthCheckType::AnnualPhysical, 'checked_on' => '2025-11-25']);
        $neverExamined = Employee::factory()->create();
        $upToDate = Employee::factory()->create();
        HealthCheck::factory()->for($upToDate)->create(['type' => HealthCheckType::AnnualPhysical, 'checked_on' => '2025-12-26']);

        expect(Employee::query()->overdueForAnnualCheck()->pluck('id')->sort()->values()->all())
            ->toBe(collect([$overdue->id, $neverExamined->id])->sort()->values()->all());
        expect(Employee::query()->dueForAnnualCheck()->pluck('id')->sort()->values()->all())
            ->toBe(collect([$overdue->id, $dueToday->id, $neverExamined->id])->sort()->values()->all());
    });

    test('counts a pre-employment exam as the first full exam', function () {
        $employee = Employee::factory()->create();
        HealthCheck::factory()->for($employee)->create(['type' => HealthCheckType::PreEmployment, 'checked_on' => '2026-10-01']);

        expect(Employee::query()->dueForAnnualCheck()->exists())->toBeFalse();
    });

    test('does not count a lab test or vitals check as the annual exam', function () {
        $employee = Employee::factory()->create();
        HealthCheck::factory()->for($employee)->create(['type' => HealthCheckType::LabTest, 'checked_on' => '2026-10-01']);

        expect(Employee::query()->overdueForAnnualCheck()->pluck('id')->all())->toBe([$employee->id]);
    });

    test('leaves inactive employees off the schedule', function () {
        Employee::factory()->inactive()->create();

        expect(Employee::query()->dueForAnnualCheck()->exists())->toBeFalse();
    });

    test('follows the configured interval', function () {
        config(['meditrack.annual_check.interval_months' => 6]);
        $employee = Employee::factory()->create();
        HealthCheck::factory()->for($employee)->create(['type' => HealthCheckType::AnnualPhysical, 'checked_on' => '2026-05-01']);

        expect(Employee::query()->overdueForAnnualCheck()->pluck('id')->all())->toBe([$employee->id]);
    });
});

describe('medical staff', function () {
    test('see counts and lists of what needs attention across every company', function () {
        $gghi = Company::firstWhere('name', 'GGHI');
        $employee = Employee::factory()->for($gghi)->create();
        HealthCheck::factory()->for($employee)->create(['type' => HealthCheckType::AnnualPhysical, 'checked_on' => '2026-06-01']);
        $overdue = FollowUp::factory()->for($employee)->overdue()->create(['reason' => 'Recheck blood pressure']);
        FollowUp::factory()->for($employee)->create(['due_on' => '2026-11-30']);
        FollowUp::factory()->for($employee)->create(['due_on' => '2026-12-20']);
        Employee::factory()->for(Company::firstWhere('name', 'Tripple2'))->create();
        HealthCheck::factory()->for($employee)->create(['type' => HealthCheckType::LabTest, 'result' => 'pending']);

        $response = $this->actingAs(User::factory()->medicalStaff()->create())->get(route('dashboard'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('view', 'medical')
            ->where('stats.overdueFollowUps', 1)
            ->where('stats.upcomingFollowUps', 1)
            ->where('stats.annualChecksOverdue', 1)
            ->where('stats.annualChecksDueSoon', 0)
            ->where('stats.awaitingResults', 1)
            ->where('stats.activeEmployees', 2)
            ->where('overdueFollowUps.0.id', $overdue->id)
            ->where('overdueFollowUps.0.reason', 'Recheck blood pressure')
            ->where('overdueFollowUps.0.employee.company', 'GGHI')
            ->has('annualChecksDue', 1)
            ->where('annualChecksDue.0.annual_check.status', 'never')
            ->has('companies', 3)
            ->where('companies.0.name', 'GGHI')
            ->where('companies.0.overdue_follow_ups_count', 1)
            ->where('companies.2.name', 'Tripple2')
            ->where('companies.2.annual_overdue_count', 1));
    });
});

describe('HR', function () {
    test('sees only their own company, without follow-up reasons', function () {
        $gghi = Company::firstWhere('name', 'GGHI');
        $ownEmployee = Employee::factory()->for($gghi)->create();
        FollowUp::factory()->for($ownEmployee)->overdue()->create(['reason' => 'Recheck blood pressure']);
        $otherEmployee = Employee::factory()->for(Company::firstWhere('name', 'GSAC Main'))->create();
        FollowUp::factory()->for($otherEmployee)->overdue()->create();

        $response = $this->actingAs(User::factory()->hr($gghi)->create())->get(route('dashboard'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('view', 'hr')
            ->where('companyName', 'GGHI')
            ->where('stats.overdueFollowUps', 1)
            ->where('stats.annualChecksOverdue', 1)
            ->where('stats.activeEmployees', 1)
            ->missing('stats.awaitingResults')
            ->missing('companies')
            ->has('overdueFollowUps.0', fn (Assert $followUp) => $followUp
                ->where('employee.id', $ownEmployee->id)
                ->missing('reason')
                ->missing('assignee')
                ->etc())
            ->has('annualChecksDue', 1));
        $response->assertDontSee('Recheck blood pressure');
    });
});

describe('employees', function () {
    test('see their own schedule and open follow-ups', function () {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        HealthCheck::factory()->for($employee)->create(['type' => HealthCheckType::AnnualPhysical, 'checked_on' => '2026-01-15']);
        FollowUp::factory()->for($employee)->create(['reason' => 'Bring fasting lab results', 'due_on' => '2026-12-02']);
        FollowUp::factory()->for(Employee::factory())->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('view', 'employee')
            ->where('ownRecord.employee.id', $employee->id)
            ->where('ownRecord.annualCheck', ['status' => 'up_to_date', 'last_checked_on' => '2026-01-15', 'due_on' => '2027-01-15'])
            ->has('ownRecord.openFollowUps', 1)
            ->where('ownRecord.openFollowUps.0.reason', 'Bring fasting lab results')
            ->missing('stats'));
    });

    test('see an empty state until their account is linked to a record', function () {
        $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('view', 'employee')
            ->where('ownRecord', null));
    });
});
