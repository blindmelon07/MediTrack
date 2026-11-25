<?php

use App\Enums\HealthCheckType;
use App\Enums\ReminderType;
use App\Models\Employee;
use App\Models\FollowUp;
use App\Models\HealthCheck;
use App\Models\ReminderLog;
use App\Models\User;
use App\Notifications\AnnualCheckReminder;
use App\Notifications\Channels\UniSmsChannel;
use App\Notifications\FollowUpReminder;
use App\Notifications\StaffDailyDigest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->travelTo('2026-11-25 07:00:00');
    config([
        'meditrack.reminders.days_before' => 3,
        'meditrack.reminders.overdue_repeat_days' => 7,
        'meditrack.annual_check.interval_months' => 12,
    ]);
});

describe('follow-up reminders', function () {
    test('remind the employee on the right days', function (string $dueOn, ReminderType $expectedType) {
        Notification::fake();
        $followUp = FollowUp::factory()->create(['due_on' => $dueOn]);

        $this->artisan('app:send-reminders')->assertSuccessful();

        Notification::assertSentTo(
            $followUp->employee,
            FollowUpReminder::class,
            fn (FollowUpReminder $notification) => $notification->type === $expectedType && $notification->followUp->is($followUp),
        );
    })->with([
        'three days before' => ['2026-11-28', ReminderType::FollowUpDueSoon],
        'on the due date' => ['2026-11-25', ReminderType::FollowUpDueToday],
        'one week overdue' => ['2026-11-18', ReminderType::FollowUpOverdue],
        'two weeks overdue' => ['2026-11-11', ReminderType::FollowUpOverdue],
    ]);

    test('stay quiet on the days in between', function (string $dueOn) {
        Notification::fake();
        FollowUp::factory()->create(['due_on' => $dueOn]);

        $this->artisan('app:send-reminders')->assertSuccessful();

        Notification::assertNotSentTo(Employee::sole(), FollowUpReminder::class);
    })->with([
        'two days before' => ['2026-11-27'],
        'four days before' => ['2026-11-29'],
        'three days overdue' => ['2026-11-22'],
    ]);

    test('skip follow-ups that are closed or belong to inactive employees', function () {
        Notification::fake();
        FollowUp::factory()->completed()->create(['due_on' => '2026-11-25']);
        FollowUp::factory()->for(Employee::factory()->inactive())->create(['due_on' => '2026-11-25']);

        $this->artisan('app:send-reminders')->assertSuccessful();

        Notification::assertNothingSentTo(Employee::all());
    });

    test('are sent only once a day even if the command runs twice', function () {
        Notification::fake();
        $followUp = FollowUp::factory()->create(['due_on' => '2026-11-25']);

        $this->artisan('app:send-reminders')->assertSuccessful();
        $this->artisan('app:send-reminders')->assertSuccessful();

        Notification::assertSentToTimes($followUp->employee, FollowUpReminder::class, 1);
        expect(ReminderLog::sole())
            ->reminder_type->toBe(ReminderType::FollowUpDueToday)
            ->subject_id->toBe($followUp->id);
    });

    test('are skipped for employees with no mobile number or email', function () {
        Notification::fake();
        FollowUp::factory()->for(Employee::factory()->state(['phone' => null, 'email' => null]))->create(['due_on' => '2026-11-25']);

        $this->artisan('app:send-reminders')
            ->expectsOutputToContain('no mobile number or email on file')
            ->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('reminder_logs', 0);
    });

    test('text the employee without revealing the medical reason', function () {
        config(['services.unisms.secret_key' => 'sk_test_123', 'services.unisms.sender_id' => 'MEDITRACK', 'app.name' => 'MediTrack']);
        Http::preventStrayRequests();
        Http::fake(['unismsapi.com/api/sms' => Http::response(['message' => ['reference_id' => 'msg_1']], 201)]);
        FollowUp::factory()
            ->for(Employee::factory()->state(['first_name' => 'Ana', 'phone' => '09171234567', 'email' => null]))
            ->create(['due_on' => '2026-11-25', 'reason' => 'Recheck HIV test']);

        $this->artisan('app:send-reminders')->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request['recipient'] === '+639171234567'
            && $request['content'] === 'Hi Ana, reminder from MediTrack: your clinic follow-up is due today, Nov 25, 2026. Please visit the clinic today.');
    });

    test('release the claim when the SMS fails, so the next run retries', function () {
        config(['services.unisms.secret_key' => 'sk_test_123', 'services.unisms.sender_id' => 'MEDITRACK']);
        Http::preventStrayRequests();
        Http::fake(['unismsapi.com/api/sms' => Http::response(['errors' => 'server error'], 500)]);
        FollowUp::factory()->for(Employee::factory()->state(['email' => null]))->create(['due_on' => '2026-11-25']);

        $this->artisan('app:send-reminders')->assertFailed();

        $this->assertDatabaseCount('reminder_logs', 0);
    });
});

describe('annual check reminders', function () {
    test('remind the employee three days before the due date', function () {
        Notification::fake();
        $employee = Employee::factory()->create();
        HealthCheck::factory()->for($employee)->create(['type' => HealthCheckType::AnnualPhysical, 'checked_on' => '2025-11-28']);

        $this->artisan('app:send-reminders')->assertSuccessful();

        Notification::assertSentTo(
            $employee,
            AnnualCheckReminder::class,
            fn (AnnualCheckReminder $notification) => $notification->type === ReminderType::AnnualCheckDueSoon
                && $notification->dueOn->toDateString() === '2026-11-28',
        );
    });

    test('do not go to employees who were never examined', function () {
        Notification::fake();
        Employee::factory()->create();

        $this->artisan('app:send-reminders')->assertSuccessful();

        Notification::assertNotSentTo(Employee::sole(), AnnualCheckReminder::class);
    });

    test('are sent by SMS and email when the employee has both', function () {
        $employee = Employee::factory()->create(['phone' => '09171234567', 'email' => 'ana@example.com']);

        expect((new AnnualCheckReminder(today(), ReminderType::AnnualCheckDueToday))->via($employee))
            ->toBe([UniSmsChannel::class, 'mail']);
    });
});

describe('medical staff daily list', function () {
    test('goes to the assigned nurse and includes unassigned follow-ups', function () {
        Notification::fake();
        $nurse = User::factory()->medicalStaff()->create();
        $otherNurse = User::factory()->medicalStaff()->create();
        $assigned = FollowUp::factory()->overdue()->create(['assigned_to' => $nurse->id]);
        $unassigned = FollowUp::factory()->create(['due_on' => '2026-11-25', 'assigned_to' => null]);
        FollowUp::factory()->overdue()->create(['assigned_to' => $otherNurse->id]);
        FollowUp::factory()->create(['due_on' => '2026-12-10', 'assigned_to' => $nurse->id]);

        $this->artisan('app:send-reminders')->assertSuccessful();

        Notification::assertSentTo(
            $nurse,
            StaffDailyDigest::class,
            fn (StaffDailyDigest $digest) => $digest->followUps->pluck('id')->sort()->values()->all()
                === collect([$assigned->id, $unassigned->id])->sort()->values()->all(),
        );
    });

    test('is not sent to HR or employees', function () {
        Notification::fake();
        $hr = User::factory()->hr()->create();
        $employeeUser = User::factory()->create();
        FollowUp::factory()->overdue()->create();

        $this->artisan('app:send-reminders')->assertSuccessful();

        Notification::assertNotSentTo([$hr, $employeeUser], StaffDailyDigest::class);
    });

    test('is not sent when nothing needs attention', function () {
        Notification::fake();
        User::factory()->medicalStaff()->create();
        FollowUp::factory()->create(['due_on' => '2026-12-10']);

        $this->artisan('app:send-reminders')->assertSuccessful();

        Notification::assertNotSentTo(User::sole(), StaffDailyDigest::class);
    });
});

test('a dry run lists reminders without sending or logging them', function () {
    Notification::fake();
    FollowUp::factory()->create(['due_on' => '2026-11-25']);

    $this->artisan('app:send-reminders', ['--dry-run' => true])
        ->expectsOutputToContain('would send via')
        ->assertSuccessful();

    Notification::assertNothingSent();
    $this->assertDatabaseCount('reminder_logs', 0);
});
