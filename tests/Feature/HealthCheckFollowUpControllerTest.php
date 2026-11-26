<?php

use App\Enums\FollowUpStatus;
use App\Models\Employee;
use App\Models\HealthCheck;
use App\Models\User;

test('schedules several follow-ups for a health check at once', function () {
    $healthCheck = HealthCheck::factory()->create();
    $nurse = User::factory()->medicalStaff()->create();

    $response = $this->actingAs($nurse)->post(route('health-checks.follow-ups.store', $healthCheck), [
        'follow_ups' => [
            ['reason' => 'Repeat BP check', 'due_on' => today()->addDays(14)->toDateString()],
            ['reason' => 'Cardiology referral', 'due_on' => today()->addDays(30)->toDateString(), 'assigned_to' => $nurse->id],
        ],
    ]);

    $response->assertRedirect(route('health-checks.show', $healthCheck));
    $followUps = $healthCheck->followUps()->orderBy('due_on')->get()
        ->map(fn ($followUp) => $followUp->only(['employee_id', 'reason', 'status', 'assigned_to']))
        ->all();
    expect($followUps)->toBe([
        ['employee_id' => $healthCheck->employee_id, 'reason' => 'Repeat BP check', 'status' => FollowUpStatus::Pending, 'assigned_to' => null],
        ['employee_id' => $healthCheck->employee_id, 'reason' => 'Cardiology referral', 'status' => FollowUpStatus::Pending, 'assigned_to' => $nurse->id],
    ]);
});

test('requires at least one follow-up', function () {
    $response = $this->actingAs(User::factory()->medicalStaff()->create())
        ->post(route('health-checks.follow-ups.store', HealthCheck::factory()->create()), ['follow_ups' => []]);

    $response->assertSessionHasErrors(['follow_ups' => 'The follow ups field is required.']);
});

test('rejects follow-ups due in the past', function () {
    $healthCheck = HealthCheck::factory()->create();

    $response = $this->actingAs(User::factory()->medicalStaff()->create())
        ->post(route('health-checks.follow-ups.store', $healthCheck), [
            'follow_ups' => [['reason' => 'Repeat BP check', 'due_on' => today()->subDay()->toDateString()]],
        ]);

    $response->assertSessionHasErrors('follow_ups.0.due_on');
    expect($healthCheck->followUps()->count())->toBe(0);
});

test('forbids HR from scheduling follow-ups', function () {
    $user = User::factory()->hr()->create();
    $healthCheck = HealthCheck::factory()
        ->for(Employee::factory()->state(['company_id' => $user->company_id]))
        ->create();

    $response = $this->actingAs($user)->post(route('health-checks.follow-ups.store', $healthCheck), [
        'follow_ups' => [['reason' => 'Repeat BP check', 'due_on' => today()->addDay()->toDateString()]],
    ]);

    $response->assertForbidden();
    expect($healthCheck->followUps()->count())->toBe(0);
});
