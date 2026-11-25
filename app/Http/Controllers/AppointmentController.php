<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentPurpose;
use App\Enums\AppointmentStatus;
use App\Enums\FollowUpStatus;
use App\Enums\Role;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Employee;
use App\Models\FollowUp;
use App\Models\User;
use App\Notifications\AppointmentNotice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AppointmentController extends Controller
{
    /**
     * Show the clinic's day schedule to staff and HR, or an employee's own appointments.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Appointment::class);

        /** @var User $user */
        $user = $request->user();

        if (! $user->managesHealthRecords() && ! $user->hasRole(Role::Hr)) {
            return $this->ownAppointments($user);
        }

        $date = $this->requestedDate($request) ?? CarbonImmutable::today();

        $appointments = Appointment::query()
            ->where('status', '!=', AppointmentStatus::Cancelled)
            ->whereDate('starts_at', $date->toDateString())
            ->with(['employee:id,employee_number,first_name,last_name,company_id,user_id', 'employee.company:id,name', 'booker:id,name'])
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn (Appointment $appointment): string => $appointment->starts_at->format('Y-m-d H:i'));

        $schedule = array_map(fn (array $slot): array => [
            ...$slot,
            'appointments' => $appointments->get($slot['starts_at'], collect())
                ->map(fn (Appointment $appointment): array => $this->scheduleEntry($appointment, $user))
                ->values()
                ->all(),
        ], Appointment::slotsOn($date));

        return Inertia::render('appointments/index', [
            'date' => $date->toDateString(),
            'schedule' => $schedule,
            'isClinicOpen' => $schedule !== [],
            'totals' => [
                'scheduled' => $appointments->flatten()->where('status', AppointmentStatus::Scheduled)->count(),
                'completed' => $appointments->flatten()->where('status', AppointmentStatus::Completed)->count(),
                'available' => collect($schedule)->where('available', true)->count(),
            ],
            'purposes' => AppointmentPurpose::options(),
            'statuses' => AppointmentStatus::options(),
        ]);
    }

    /**
     * Show the booking form: who, why, which day, and that day's open slots.
     */
    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $employees = $this->bookableEmployees($user);

        abort_if($employees->isEmpty(), 403);

        $selectedEmployeeId = $employees->contains('id', $request->integer('employee_id'))
            ? $request->integer('employee_id')
            : ($employees->count() === 1 ? $employees->first()->id : null);

        $followUp = $request->filled('follow_up_id') && $selectedEmployeeId !== null
            ? FollowUp::query()
                ->where('employee_id', $selectedEmployeeId)
                ->where('status', FollowUpStatus::Pending)
                ->find($request->integer('follow_up_id'), ['id', 'due_on'])
            : null;

        $date = $this->requestedDate($request) ?? Appointment::nextOpenDate(CarbonImmutable::today());
        $slots = Appointment::slotsOn($date);
        $purpose = AppointmentPurpose::tryFrom((string) $request->query('purpose'))
            ?? ($followUp !== null ? AppointmentPurpose::FollowUp : AppointmentPurpose::AnnualCheck);
        $requestedStartsAt = (string) $request->query('starts_at');
        $selectedStartsAt = collect($slots)->contains(fn (array $slot): bool => $slot['available'] && $slot['starts_at'] === $requestedStartsAt)
            ? $requestedStartsAt
            : null;

        return Inertia::render('appointments/create', [
            'employees' => $employees,
            'selectedEmployeeId' => $selectedEmployeeId,
            'followUp' => $followUp?->only(['id', 'due_on']),
            'date' => $date->toDateString(),
            'lastBookableDate' => CarbonImmutable::today()->addDays((int) config('meditrack.clinic.booking_window_days'))->toDateString(),
            'slots' => $slots,
            'selectedStartsAt' => $selectedStartsAt,
            'purposes' => AppointmentPurpose::options(),
            'purpose' => $purpose->value,
            'canAddNotes' => ! $user->hasRole(Role::Hr),
        ]);
    }

    /**
     * Book the appointment, then confirm it to the employee by SMS and email.
     */
    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $employee = $request->employee();
        $startsAt = CarbonImmutable::createFromFormat('Y-m-d H:i', $request->validated('starts_at'))->startOfMinute();

        $appointment = DB::transaction(function () use ($request, $user, $employee, $startsAt): Appointment {
            // Lock the employee and anyone already in the slot so two people cannot take the last place at once.
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();
            Appointment::query()->where('starts_at', $startsAt->format('Y-m-d H:i:s'))->lockForUpdate()->get();

            $problem = Appointment::bookingProblem($startsAt, $employee);

            if ($problem !== null) {
                throw ValidationException::withMessages(['starts_at' => $problem]);
            }

            $appointment = new Appointment([
                'employee_id' => $employee->id,
                'follow_up_id' => $request->validated('follow_up_id'),
                'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                'purpose' => $request->validated('purpose'),
                'notes' => $user->hasRole(Role::Hr) ? null : $request->validated('notes'),
            ]);
            $appointment->booked_by = $user->id;
            $appointment->save();

            return $appointment;
        });

        $this->notifyEmployee($appointment, AppointmentNotice::BOOKED);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Appointment booked for :when.', [
            'when' => $startsAt->format('M j, g:i A'),
        ])]);

        return to_route('appointments.index', $this->ownsOnly($user) ? [] : ['date' => $startsAt->toDateString()]);
    }

    /**
     * Cancel the appointment or record whether the employee came.
     */
    public function update(UpdateAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $status = $request->enum('status', AppointmentStatus::class);

        $appointment->update(['status' => $status]);

        if ($status === AppointmentStatus::Cancelled && $appointment->employee->user_id !== $user->id) {
            $this->notifyEmployee($appointment, AppointmentNotice::CANCELLED);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => match ($status) {
            AppointmentStatus::Cancelled => __('Appointment cancelled.'),
            AppointmentStatus::NoShow => __('Marked as no-show.'),
            default => __('Marked as completed.'),
        }]);

        return back();
    }

    /**
     * Show an employee their upcoming and recent appointments.
     */
    private function ownAppointments(User $user): Response
    {
        $employee = $user->employee;

        $appointments = $employee === null
            ? new Collection
            : $employee->appointments()->with('employee:id,company_id,user_id')->orderByDesc('starts_at')->limit(30)->get();

        $toRow = fn (Appointment $appointment): array => [
            'id' => $appointment->id,
            'starts_at' => $appointment->starts_at->format('Y-m-d H:i'),
            'notes' => $appointment->notes,
            'purpose' => $appointment->purpose->value,
            'status' => $appointment->status->value,
            'can' => ['cancel' => $user->can('cancel', $appointment)],
        ];

        [$upcoming, $past] = $appointments->partition(
            fn (Appointment $appointment): bool => $appointment->status === AppointmentStatus::Scheduled && $appointment->starts_at->isFuture(),
        );

        return Inertia::render('appointments/mine', [
            'upcoming' => $upcoming->sortBy('starts_at')->map($toRow)->values(),
            'past' => $past->map($toRow)->values(),
            'canBook' => $employee !== null && $user->can('create', [Appointment::class, $employee]),
            'purposes' => AppointmentPurpose::options(),
            'statuses' => AppointmentStatus::options(),
        ]);
    }

    /**
     * Shape an appointment for the day schedule. HR sees names only for their own company,
     * and notes stay with medical staff.
     *
     * @return array<string, mixed>
     */
    private function scheduleEntry(Appointment $appointment, User $user): array
    {
        $employee = $appointment->employee;
        $canSeeWho = $user->managesHealthRecords() || $user->isHrFor($employee->company_id);

        return [
            'id' => $appointment->id,
            'status' => $appointment->status->value,
            'purpose' => $canSeeWho ? $appointment->purpose->value : null,
            'employee' => $canSeeWho ? [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'company' => $employee->company?->name,
            ] : null,
            'notes' => $user->managesHealthRecords() ? $appointment->notes : null,
            'booked_by' => $canSeeWho ? $appointment->booker?->name : null,
            'can' => [
                'cancel' => $user->can('cancel', $appointment),
                'recordOutcome' => $user->can('recordOutcome', $appointment),
            ],
        ];
    }

    /**
     * Get the active employees the user may book for.
     *
     * @return Collection<int, Employee>
     */
    private function bookableEmployees(User $user): Collection
    {
        return Employee::query()
            ->active()
            ->visibleTo($user)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'employee_number', 'first_name', 'last_name']);
    }

    /**
     * Read the ?date= filter when it is a real date.
     */
    private function requestedDate(Request $request): ?CarbonImmutable
    {
        $date = (string) $request->query('date');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))
            ? CarbonImmutable::parse($date)
            : null;
    }

    /**
     * Determine whether the user only ever sees their own appointments.
     */
    private function ownsOnly(User $user): bool
    {
        return ! $user->managesHealthRecords() && ! $user->hasRole(Role::Hr);
    }

    /**
     * Send a booking notice without failing the request if SMS or email is down.
     *
     * @param  AppointmentNotice::BOOKED|AppointmentNotice::CANCELLED  $event
     */
    private function notifyEmployee(Appointment $appointment, string $event): void
    {
        try {
            $appointment->employee->notify(new AppointmentNotice($appointment, $event));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
