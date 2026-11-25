<?php

use App\Enums\HealthCheckType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FollowUp;
use App\Models\HealthCheck;
use App\Models\User;
use App\Notifications\HrWeeklyDigest;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->travelTo('2026-11-30 07:00:00');
});

test('emails HR their own company\'s overdue annual checks and follow-ups', function () {
    Notification::fake();
    $gghi = Company::firstWhere('name', 'GGHI');
    $hr = User::factory()->hr($gghi)->create();
    $neverExamined = Employee::factory()->for($gghi)->create();
    $upToDate = Employee::factory()->for($gghi)->create();
    HealthCheck::factory()->for($upToDate)->create(['type' => HealthCheckType::AnnualPhysical, 'checked_on' => '2026-10-01']);
    $overdueFollowUp = FollowUp::factory()->for($upToDate)->overdue()->create();
    Employee::factory()->for(Company::firstWhere('name', 'Tripple2'))->create();

    $this->artisan('app:send-hr-digest')->assertSuccessful();

    Notification::assertSentTo($hr, HrWeeklyDigest::class, fn (HrWeeklyDigest $digest) => $digest->companyName === 'GGHI'
        && $digest->annualChecksOverdue->pluck('id')->all() === [$neverExamined->id]
        && $digest->overdueFollowUps->pluck('id')->all() === [$overdueFollowUp->id]);
});

test('never includes follow-up reasons in the email', function () {
    $gghi = Company::firstWhere('name', 'GGHI');
    $hr = User::factory()->hr($gghi)->create();
    FollowUp::factory()
        ->for(Employee::factory()->for($gghi)->state(['first_name' => 'Ana', 'last_name' => 'Santos']))
        ->overdue()
        ->create(['reason' => 'Recheck HIV test']);

    $mail = (new HrWeeklyDigest('GGHI', Employee::query()->whereKey([])->get(), FollowUp::query()->with('employee')->get()))->toMail($hr);
    $rendered = implode("\n", $mail->introLines);

    expect($rendered)->toContain('Santos, Ana')->not->toContain('Recheck HIV test');
});

test('skips HR users whose company has nothing overdue', function () {
    Notification::fake();
    $gghi = Company::firstWhere('name', 'GGHI');
    $hr = User::factory()->hr($gghi)->create();
    $employee = Employee::factory()->for($gghi)->create();
    HealthCheck::factory()->for($employee)->create(['type' => HealthCheckType::AnnualPhysical, 'checked_on' => '2026-10-01']);

    $this->artisan('app:send-hr-digest')->assertSuccessful();

    Notification::assertNothingSentTo($hr);
});
