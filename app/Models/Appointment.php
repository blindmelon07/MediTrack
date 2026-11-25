<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\AppointmentPurpose;
use App\Enums\AppointmentStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A clinic visit booked into one of the clinic's time slots.
 *
 * @property int $id
 * @property int $employee_id
 * @property int|null $follow_up_id
 * @property int|null $booked_by
 * @property CarbonImmutable $starts_at
 * @property AppointmentPurpose $purpose
 * @property AppointmentStatus $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['employee_id', 'follow_up_id', 'starts_at', 'purpose', 'status', 'notes'])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use Auditable, HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'scheduled',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime:Y-m-d H:i',
            'purpose' => AppointmentPurpose::class,
            'status' => AppointmentStatus::class,
        ];
    }

    /**
     * Describe the appointment for the audit log.
     */
    public function auditLabel(): string
    {
        return "appointment #{$this->id} on {$this->starts_at->format('Y-m-d H:i')}";
    }

    /**
     * Limit the query to appointments that still hold their slot.
     *
     * @param  Builder<Appointment>  $query
     */
    #[Scope]
    protected function scheduled(Builder $query): void
    {
        $query->where('status', AppointmentStatus::Scheduled);
    }

    /**
     * List the slot start times on a date, or none when the clinic is closed that day.
     *
     * @return array<int, CarbonImmutable>
     */
    public static function slotTimesOn(CarbonInterface $date): array
    {
        $clinic = config('meditrack.clinic');
        $day = CarbonImmutable::parse($date->toDateString());

        if (! in_array(strtolower($day->englishDayOfWeek), $clinic['days'], true)) {
            return [];
        }

        $at = fn (string $time): CarbonImmutable => CarbonImmutable::parse("{$day->toDateString()} {$time}");
        $closesAt = $at($clinic['closes_at']);
        $breakStartsAt = $at($clinic['break_starts_at']);
        $breakEndsAt = $at($clinic['break_ends_at']);
        $slotMinutes = max(5, (int) $clinic['slot_minutes']);

        $slots = [];
        for ($start = $at($clinic['opens_at']); $start->addMinutes($slotMinutes)->lte($closesAt); $start = $start->addMinutes($slotMinutes)) {
            $overlapsBreak = $start->lt($breakEndsAt) && $start->addMinutes($slotMinutes)->gt($breakStartsAt);

            if (! $overlapsBreak) {
                $slots[] = $start;
            }
        }

        return $slots;
    }

    /**
     * Describe each slot on a date with how many people are booked and whether it can still be booked.
     *
     * @return array<int, array{starts_at: string, time: string, booked: int, available: bool}>
     */
    public static function slotsOn(CarbonInterface $date): array
    {
        $capacity = (int) config('meditrack.clinic.patients_per_slot');
        $bookedCounts = self::query()
            ->scheduled()
            ->whereDate('starts_at', $date->toDateString())
            ->get(['starts_at'])
            ->countBy(fn (Appointment $appointment): string => $appointment->starts_at->format('Y-m-d H:i'));

        return array_map(function (CarbonImmutable $start) use ($bookedCounts, $capacity): array {
            $key = $start->format('Y-m-d H:i');
            $booked = (int) $bookedCounts->get($key, 0);

            return [
                'starts_at' => $key,
                'time' => $start->format('g:i A'),
                'booked' => $booked,
                'available' => $start->isFuture() && $booked < $capacity,
            ];
        }, self::slotTimesOn($date));
    }

    /**
     * Explain why the employee cannot be booked at this time, or return null when they can.
     */
    public static function bookingProblem(CarbonImmutable $startsAt, Employee $employee): ?string
    {
        if (! $startsAt->isFuture()) {
            return 'That time has already passed. Choose a later slot.';
        }

        if ($startsAt->startOfDay()->gt(today()->addDays((int) config('meditrack.clinic.booking_window_days')))) {
            return 'Appointments can only be booked up to '.config('meditrack.clinic.booking_window_days').' days ahead.';
        }

        $isSlot = collect(self::slotTimesOn($startsAt))->contains(fn (CarbonImmutable $slot): bool => $slot->equalTo($startsAt));

        if (! $isSlot) {
            return 'The clinic is not open for appointments at that time.';
        }

        $booked = self::query()->scheduled()->where('starts_at', $startsAt->format('Y-m-d H:i:s'))->count();

        if ($booked >= (int) config('meditrack.clinic.patients_per_slot')) {
            return 'That slot was just taken. Choose another time.';
        }

        $hasSameDayBooking = self::query()
            ->scheduled()
            ->whereBelongsTo($employee)
            ->whereDate('starts_at', $startsAt->toDateString())
            ->exists();

        if ($hasSameDayBooking) {
            return "{$employee->first_name} already has a clinic appointment that day.";
        }

        return null;
    }

    /**
     * Get the first date on or after the given day when the clinic is open.
     */
    public static function nextOpenDate(CarbonImmutable $from): CarbonImmutable
    {
        for ($day = $from->startOfDay(), $i = 0; $i < 7; $day = $day->addDay(), $i++) {
            if (self::slotTimesOn($day) !== []) {
                return $day;
            }
        }

        return $from->startOfDay();
    }

    /**
     * Get the employee the appointment is for.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the follow-up this visit is for, if any.
     *
     * @return BelongsTo<FollowUp, $this>
     */
    public function followUp(): BelongsTo
    {
        return $this->belongsTo(FollowUp::class);
    }

    /**
     * Get the user who booked the appointment.
     *
     * @return BelongsTo<User, $this>
     */
    public function booker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }
}
