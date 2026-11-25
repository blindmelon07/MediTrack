<?php

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\AppointmentNotice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Thursday morning, Manila time.
    $this->travelTo('2026-11-26 09:10:00');
    config(['meditrack.clinic' => [
        'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        'opens_at' => '08:00',
        'closes_at' => '17:00',
        'break_starts_at' => '12:00',
        'break_ends_at' => '13:00',
        'slot_minutes' => 30,
        'patients_per_slot' => 1,
        'booking_window_days' => 60,
    ]]);
    Notification::fake();
});

/**
 * @return array<string, mixed>
 */
function bookingPayload(Employee $employee, string $startsAt = '2026-11-27 09:00', array $overrides = []): array
{
    return ['employee_id' => $employee->id, 'starts_at' => $startsAt, 'purpose' => 'annual_check', ...$overrides];
}

describe('clinic slots', function () {
    test('run from opening to closing in slot-sized steps, skipping the lunch break', function () {
        $times = array_map(
            fn (CarbonImmutable $slot): string => $slot->format('H:i'),
            Appointment::slotTimesOn(CarbonImmutable::parse('2026-11-27')),
        );

        expect($times)->toBe([
            '08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
            '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30',
        ]);
    });

    test('are empty on days the clinic is closed', function () {
        config(['meditrack.clinic.days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']]);

        expect(Appointment::slotTimesOn(CarbonImmutable::parse('2026-11-29')))->toBe([]);
    });

    test('are unavailable once passed or full', function () {
        Appointment::factory()->at('2026-11-26 10:00:00')->create();

        $slots = collect(Appointment::slotsOn(CarbonImmutable::parse('2026-11-26')))->keyBy('starts_at');

        expect($slots['2026-11-26 09:00']['available'])->toBeFalse()
            ->and($slots['2026-11-26 09:30']['available'])->toBeTrue()
            ->and($slots['2026-11-26 10:00']['available'])->toBeFalse()
            ->and($slots['2026-11-26 10:00']['booked'])->toBe(1);
    });

    test('free up again when an appointment is cancelled', function () {
        Appointment::factory()->cancelled()->at('2026-11-26 10:00:00')->create();

        $slots = collect(Appointment::slotsOn(CarbonImmutable::parse('2026-11-26')))->keyBy('starts_at');

        expect($slots['2026-11-26 10:00']['available'])->toBeTrue();
    });
});

describe('booking', function () {
    test('medical staff book any employee and the employee is told', function () {
        $nurse = User::factory()->medicalStaff()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($nurse)->post(route('appointments.store'), bookingPayload($employee, overrides: ['notes' => 'Fasting required']));

        $appointment = Appointment::sole();
        expect($appointment)
            ->employee_id->toBe($employee->id)
            ->booked_by->toBe($nurse->id)
            ->notes->toBe('Fasting required')
            ->status->toBe(AppointmentStatus::Scheduled)
            ->and($appointment->starts_at->format('Y-m-d H:i'))->toBe('2026-11-27 09:00');
        $response->assertSessionHasNoErrors()->assertRedirect(route('appointments.index', ['date' => '2026-11-27']));
        Notification::assertSentTo($employee, AppointmentNotice::class, fn (AppointmentNotice $notice) => $notice->event === AppointmentNotice::BOOKED);
        expect(AuditLog::query()->where('subject_type', $appointment->getMorphClass())->where('action', AuditAction::Created)->exists())->toBeTrue();
    });

    test('HR book their own company\'s employees, without notes', function () {
        $company = Company::factory()->create();
        $employee = Employee::factory()->for($company)->create();

        $this->actingAs(User::factory()->hr($company)->create())
            ->post(route('appointments.store'), bookingPayload($employee, overrides: ['notes' => 'HR note']))
            ->assertSessionHasNoErrors();

        expect(Appointment::sole()->notes)->toBeNull();
    });

    test('forbids HR from booking another company\'s employee', function () {
        $employee = Employee::factory()->create();

        $response = $this->actingAs(User::factory()->hr()->create())->post(route('appointments.store'), bookingPayload($employee));

        $response->assertForbidden();
        $this->assertDatabaseCount('appointments', 0);
    });

    test('employees book themselves', function () {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('appointments.store'), bookingPayload($employee));

        $response->assertRedirect(route('appointments.index'));
        expect(Appointment::sole()->employee_id)->toBe($employee->id);
    });

    test('forbids employees from booking a coworker', function () {
        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);
        $coworker = Employee::factory()->create();

        $response = $this->actingAs($user)->post(route('appointments.store'), bookingPayload($coworker));

        $response->assertForbidden();
    });

    test('forbids booking an inactive employee', function () {
        $employee = Employee::factory()->inactive()->create();

        $response = $this->actingAs(User::factory()->medicalStaff()->create())->post(route('appointments.store'), bookingPayload($employee));

        $response->assertForbidden();
    });

    test('rejects times the clinic cannot take', function (string $startsAt, string $message) {
        $employee = Employee::factory()->create();

        $response = $this->actingAs(User::factory()->medicalStaff()->create())
            ->post(route('appointments.store'), bookingPayload($employee, $startsAt));

        $response->assertSessionHasErrors(['starts_at' => $message]);
        $this->assertDatabaseCount('appointments', 0);
    })->with([
        'already passed' => ['2026-11-26 08:30', 'That time has already passed. Choose a later slot.'],
        'lunch break' => ['2026-11-27 12:00', 'The clinic is not open for appointments at that time.'],
        'after closing' => ['2026-11-27 17:00', 'The clinic is not open for appointments at that time.'],
        'off the slot grid' => ['2026-11-27 09:15', 'The clinic is not open for appointments at that time.'],
        'too far ahead' => ['2027-02-01 09:00', 'Appointments can only be booked up to 60 days ahead.'],
    ]);

    test('rejects a slot that is already full', function () {
        Appointment::factory()->at('2026-11-27 09:00:00')->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs(User::factory()->medicalStaff()->create())->post(route('appointments.store'), bookingPayload($employee));

        $response->assertSessionHasErrors(['starts_at' => 'That slot was just taken. Choose another time.']);
        $this->assertDatabaseCount('appointments', 1);
    });

    test('rejects a second appointment for the same employee on the same day', function () {
        $employee = Employee::factory()->create(['first_name' => 'Ana']);
        Appointment::factory()->for($employee)->at('2026-11-27 14:00:00')->create();

        $response = $this->actingAs(User::factory()->medicalStaff()->create())->post(route('appointments.store'), bookingPayload($employee));

        $response->assertSessionHasErrors(['starts_at' => 'Ana already has a clinic appointment that day.']);
    });
});

describe('cancelling and outcomes', function () {
    test('employees cancel their own upcoming appointment without notifying themselves', function () {
        $user = User::factory()->create();
        $appointment = Appointment::factory()->for(Employee::factory()->state(['user_id' => $user->id]))->create();

        $this->actingAs($user)->put(route('appointments.update', $appointment), ['status' => 'cancelled'])->assertRedirect();

        expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled);
        Notification::assertNothingSent();
    });

    test('notifies the employee when staff cancel', function () {
        $appointment = Appointment::factory()->create();

        $this->actingAs(User::factory()->medicalStaff()->create())
            ->put(route('appointments.update', $appointment), ['status' => 'cancelled']);

        Notification::assertSentTo($appointment->employee, AppointmentNotice::class, fn (AppointmentNotice $notice) => $notice->event === AppointmentNotice::CANCELLED);
    });

    test('cannot cancel an appointment that has already started', function () {
        $user = User::factory()->create();
        $appointment = Appointment::factory()->for(Employee::factory()->state(['user_id' => $user->id]))->at('2026-11-26 09:00:00')->create();

        $response = $this->actingAs($user)->put(route('appointments.update', $appointment), ['status' => 'cancelled']);

        $response->assertForbidden();
        expect($appointment->fresh()->status)->toBe(AppointmentStatus::Scheduled);
    });

    test('medical staff record no-shows', function () {
        $appointment = Appointment::factory()->at('2026-11-26 08:00:00')->create();

        $this->actingAs(User::factory()->medicalStaff()->create())
            ->put(route('appointments.update', $appointment), ['status' => 'no_show']);

        expect($appointment->fresh()->status)->toBe(AppointmentStatus::NoShow);
    });

    test('forbids HR from recording outcomes', function () {
        $company = Company::factory()->create();
        $appointment = Appointment::factory()->for(Employee::factory()->for($company))->create();

        $response = $this->actingAs(User::factory()->hr($company)->create())
            ->put(route('appointments.update', $appointment), ['status' => 'completed']);

        $response->assertForbidden();
    });

    test('marks today\'s appointment completed when the health check is recorded', function () {
        $employee = Employee::factory()->create();
        $appointment = Appointment::factory()->for($employee)->at('2026-11-26 10:00:00')->create();

        $this->actingAs(User::factory()->medicalStaff()->create())->post(route('health-checks.store'), [
            'employee_id' => $employee->id,
            'type' => 'annual_physical',
            'checked_on' => '2026-11-26',
            'result' => 'normal',
        ])->assertSessionHasNoErrors();

        expect($appointment->fresh()->status)->toBe(AppointmentStatus::Completed);
    });
});

describe('schedule views', function () {
    test('HR see their own company\'s bookings by name and others only as booked', function () {
        $company = Company::factory()->create();
        $own = Appointment::factory()->for(Employee::factory()->for($company))->at('2026-11-26 10:00:00')->create(['notes' => 'Bring X-ray']);
        Appointment::factory()->at('2026-11-26 10:30:00')->create();

        $response = $this->actingAs(User::factory()->hr($company)->create())->get(route('appointments.index'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('appointments/index')
            ->where('schedule.4.appointments.0.employee.id', $own->employee_id)
            ->where('schedule.4.appointments.0.notes', null)
            ->where('schedule.5.appointments.0.employee', null)
            ->where('schedule.5.appointments.0.purpose', null));
    });

    test('employees see only their own appointments', function () {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $mine = Appointment::factory()->for($employee)->create();
        Appointment::factory()->at('2026-11-27 10:00:00')->create();

        $response = $this->actingAs($user)->get(route('appointments.index'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('appointments/mine')
            ->has('upcoming', 1)
            ->where('upcoming.0.id', $mine->id)
            ->where('upcoming.0.starts_at', '2026-11-27 09:00')
            ->where('upcoming.0.can.cancel', true));
    });

    test('pre-selects the slot chosen on the schedule', function () {
        $employee = Employee::factory()->create();

        $response = $this->actingAs(User::factory()->medicalStaff()->create())
            ->get(route('appointments.create', ['employee_id' => $employee->id, 'date' => '2026-11-27', 'starts_at' => '2026-11-27 13:30']));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('appointments/create')
            ->where('selectedEmployeeId', $employee->id)
            ->where('selectedStartsAt', '2026-11-27 13:30')
            ->has('slots', 16));
    });
});

test('reminds employees the day before their appointment', function () {
    $appointment = Appointment::factory()->create();

    $this->artisan('app:send-reminders')->assertSuccessful();

    Notification::assertSentTo($appointment->employee, AppointmentNotice::class, fn (AppointmentNotice $notice) => $notice->event === AppointmentNotice::TOMORROW);
});
