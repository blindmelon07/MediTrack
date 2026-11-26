<?php

use App\Actions\Ai\AiUnavailableException;
use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\FollowUp;
use App\Models\HealthCheck;
use App\Models\User;
use Tests\Fakes\FakeAiAssistant;

test('drafts a summary and dated follow-ups for medical staff without saving them', function () {
    $this->freezeTime();
    $assistant = FakeAiAssistant::swap([
        'summary' => 'Blood pressure is elevated; the employee is fit to work.',
        'follow_ups' => [['reason' => 'Repeat BP check', 'due_in_days' => 14]],
    ]);
    $healthCheck = HealthCheck::factory()->create();

    $response = $this->actingAs(User::factory()->medicalStaff()->create())
        ->postJson(route('health-checks.ai-summary', $healthCheck));

    $response->assertOk()->assertExactJson([
        'summary' => 'Blood pressure is elevated; the employee is fit to work.',
        'follow_ups' => [['reason' => 'Repeat BP check', 'due_on' => today()->addDays(14)->toDateString()]],
    ]);
    expect($assistant->requests)->toHaveCount(1);
    expect(FollowUp::count())->toBe(0);
    expect(AuditLog::where('action', AuditAction::AiDrafted)->where('subject_id', $healthCheck->id)->exists())->toBeTrue();
});

test('keeps suggested follow-up dates between tomorrow and a year ahead', function () {
    $this->freezeTime();
    FakeAiAssistant::swap([
        'summary' => 'Summary.',
        'follow_ups' => [
            ['reason' => 'Too soon', 'due_in_days' => 0],
            ['reason' => 'Too far', 'due_in_days' => 9999],
        ],
    ]);

    $response = $this->actingAs(User::factory()->medicalStaff()->create())
        ->postJson(route('health-checks.ai-summary', HealthCheck::factory()->create()));

    $response->assertOk()->assertJsonPath('follow_ups', [
        ['reason' => 'Too soon', 'due_on' => today()->addDay()->toDateString()],
        ['reason' => 'Too far', 'due_on' => today()->addDays(365)->toDateString()],
    ]);
});

test('sends no name, employee number, email or phone to the AI', function () {
    $assistant = FakeAiAssistant::swap(['summary' => 'Summary.', 'follow_ups' => []]);
    $employee = Employee::factory()->create([
        'first_name' => 'Maricel',
        'last_name' => 'Dimaculangan',
        'employee_number' => 'EMP-48213',
        'email' => 'maricel.d@example.com',
        'phone' => '+639171234567',
    ]);
    $healthCheck = HealthCheck::factory()->for($employee)->create([
        'findings' => 'Maricel Dimaculangan reports headaches. Call 0917-123-4567 or maricel.d@example.com.',
        'recommendations' => 'Dimaculangan to return for BP check (EMP-48213).',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('health-checks.ai-summary', $healthCheck))
        ->assertOk();

    $sent = $assistant->sentText();
    expect($sent)
        ->not->toContain('Maricel')
        ->not->toContain('Dimaculangan')
        ->not->toContain('EMP-48213')
        ->not->toContain('maricel.d@example.com')
        ->not->toContain('0917-123-4567')
        ->toContain('[patient] reports headaches');
});

test('forbids HR from drafting with AI', function () {
    $assistant = FakeAiAssistant::swap(['summary' => 'Summary.', 'follow_ups' => []]);
    $user = User::factory()->hr()->create();
    $healthCheck = HealthCheck::factory()
        ->for(Employee::factory()->state(['company_id' => $user->company_id]))
        ->create();

    $response = $this->actingAs($user)->postJson(route('health-checks.ai-summary', $healthCheck));

    $response->assertForbidden();
    expect($assistant->requests)->toBeEmpty();
});

test('tells staff when the AI could not answer', function () {
    FakeAiAssistant::swap(AiUnavailableException::unreachable());

    $response = $this->actingAs(User::factory()->medicalStaff()->create())
        ->postJson(route('health-checks.ai-summary', HealthCheck::factory()->create()));

    $response->assertServiceUnavailable()
        ->assertJson(['message' => 'The AI assistant is not responding right now. Please try again in a moment.']);
    expect(AuditLog::where('action', AuditAction::AiDrafted)->exists())->toBeFalse();
});

test('reports AI as not set up when it is switched off', function () {
    config(['meditrack.ai.enabled' => false, 'services.anthropic.key' => 'test-key']);

    $response = $this->actingAs(User::factory()->medicalStaff()->create())
        ->postJson(route('health-checks.ai-summary', HealthCheck::factory()->create()));

    $response->assertServiceUnavailable()->assertJson(['message' => 'AI assistance is not set up yet.']);
});

test('reports AI as not set up when there is no API key', function () {
    config(['meditrack.ai.enabled' => true, 'services.anthropic.key' => null]);

    $response = $this->actingAs(User::factory()->medicalStaff()->create())
        ->postJson(route('health-checks.ai-summary', HealthCheck::factory()->create()));

    $response->assertServiceUnavailable()->assertJson(['message' => 'AI assistance is not set up yet.']);
});

test('offers AI only to staff who manage health records while it is available', function () {
    config(['meditrack.ai.enabled' => true, 'services.anthropic.key' => 'test-key']);

    $this->actingAs(User::factory()->medicalStaff()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.can.useAi', true));

    $this->actingAs(User::factory()->hr()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.can.useAi', false));

    config(['meditrack.ai.enabled' => false]);

    $this->actingAs(User::factory()->medicalStaff()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.can.useAi', false));
});
