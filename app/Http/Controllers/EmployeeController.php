<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentPurpose;
use App\Enums\AuditAction;
use App\Enums\FollowUpStatus;
use App\Enums\HealthCheckResult;
use App\Enums\HealthCheckType;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FollowUp;
use App\Models\HealthCheck;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    /**
     * List the employees the user may see, filtered by search term, company, and employment status.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Employee::class);

        /** @var User $user */
        $user = $request->user();
        $showMedicalResults = $user->managesHealthRecords();
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status', 'active')->toString();
        $companyId = $showMedicalResults ? ($request->integer('company_id') ?: null) : null;
        $annual = $request->string('annual')->toString();

        $employees = Employee::query()
            ->visibleTo($user)
            ->search($search)
            ->when($status === 'active', fn ($query) => $query->active())
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($companyId, fn ($query, int $companyId) => $query->where('company_id', $companyId))
            ->when($annual === 'due', fn ($query) => $query->dueForAnnualCheck())
            ->when($annual === 'overdue', fn ($query) => $query->overdueForAnnualCheck())
            ->withLastAnnualCheck()
            ->with(['company:id,name', 'latestHealthCheck'])
            ->withCount([
                'followUps as pending_follow_ups_count' => fn ($query) => $query->where('status', FollowUpStatus::Pending),
                'followUps as overdue_follow_ups_count' => fn ($query) => $query->overdue(),
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Employee $employee): array => $this->listRow($employee, $showMedicalResults));

        return Inertia::render('employees/index', [
            'employees' => $employees,
            'filters' => ['search' => $search, 'status' => $status, 'company_id' => $companyId, 'annual' => $annual],
            'companies' => $showMedicalResults ? Company::query()->orderBy('name')->get(['id', 'name']) : [],
            'healthCheckTypes' => HealthCheckType::options(),
            'healthCheckResults' => HealthCheckResult::options(),
        ]);
    }

    /**
     * Show the form for adding an employee.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Employee::class);

        return Inertia::render('employees/create', [
            'companies' => $this->companyOptions($request->user()),
        ]);
    }

    /**
     * Save a new employee.
     */
    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $employee = Employee::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee added.')]);

        return to_route('employees.show', $employee);
    }

    /**
     * Show the employee's profile. Medical staff and the employee themselves see the full
     * health history; HR sees only clearance and follow-up due dates.
     */
    public function show(Employee $employee): Response
    {
        Gate::authorize('view', $employee);

        $employee->loadMax(
            ['healthChecks as last_annual_check_on' => fn ($query) => $query->whereIn('type', HealthCheckType::annualCheckTypes())],
            'checked_on',
        );

        $canViewMedicalRecords = Gate::allows('viewMedicalRecords', $employee);

        AuditLog::record(
            AuditAction::Viewed,
            $employee,
            ($canViewMedicalRecords ? 'Viewed medical record of ' : 'Viewed clearance summary of ').$employee->auditLabel(),
        );

        $healthChecks = $employee->healthChecks()
            ->withCount('attachments')
            ->orderByDesc('checked_on')
            ->get();

        $followUps = $employee->followUps()
            ->with('assignee:id,name')
            ->orderByDesc('due_on')
            ->get();

        return Inertia::render('employees/show', [
            'employee' => $employee->load('company:id,name'),
            'annualCheck' => $employee->annualCheckSummary(),
            'canViewMedicalRecords' => $canViewMedicalRecords,
            'can' => [
                'update' => Gate::allows('update', $employee),
                'manageMedicalRecords' => Gate::allows('manageMedicalRecords', $employee),
                'bookAppointment' => Gate::allows('create', [Appointment::class, $employee]),
            ],
            'upcomingAppointments' => $employee->appointments()
                ->scheduled()
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')
                ->limit(5)
                ->get()
                ->map(fn (Appointment $appointment): array => [
                    'id' => $appointment->id,
                    'starts_at' => $appointment->starts_at->format('Y-m-d H:i'),
                    'purpose' => $appointment->purpose->value,
                ]),
            'appointmentPurposes' => AppointmentPurpose::options(),
            'healthChecks' => $canViewMedicalRecords ? $healthChecks : $this->clearanceSummaries($healthChecks),
            'followUps' => $canViewMedicalRecords ? $followUps : $this->followUpSchedule($followUps),
            'healthCheckTypes' => HealthCheckType::options(),
            'healthCheckResults' => HealthCheckResult::options(),
            'followUpStatuses' => FollowUpStatus::options(),
        ]);
    }

    /**
     * Show the form for editing an employee.
     */
    public function edit(Request $request, Employee $employee): Response
    {
        Gate::authorize('update', $employee);

        return Inertia::render('employees/edit', [
            'employee' => $employee,
            'companies' => $this->companyOptions($request->user()),
        ]);
    }

    /**
     * Update an employee's details.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee updated.')]);

        return to_route('employees.show', $employee);
    }

    /**
     * Shape an employee for the list. The medical result is only included for medical staff.
     *
     * @return array<string, mixed>
     */
    private function listRow(Employee $employee, bool $showMedicalResult): array
    {
        $latestCheck = $employee->latestHealthCheck;

        return [
            ...$employee->only(['id', 'employee_number', 'full_name', 'department', 'position', 'is_active']),
            'company' => $employee->company?->name,
            'pending_follow_ups_count' => $employee->pending_follow_ups_count,
            'overdue_follow_ups_count' => $employee->overdue_follow_ups_count,
            'annual_check' => $employee->annualCheckSummary(),
            'latest_check' => $latestCheck === null ? null : [
                'checked_on' => $latestCheck->checked_on->toDateString(),
                'type' => $latestCheck->type->value,
                'clearance' => $latestCheck->result->clearance(),
                'result' => $showMedicalResult ? $latestCheck->result->value : null,
            ],
        ];
    }

    /**
     * Reduce health checks to what HR may see: when, what kind, and whether the employee is cleared.
     *
     * @param  Collection<int, HealthCheck>  $healthChecks
     * @return array<int, array<string, mixed>>
     */
    private function clearanceSummaries(Collection $healthChecks): array
    {
        return $healthChecks->map(fn (HealthCheck $healthCheck): array => [
            'id' => $healthCheck->id,
            'checked_on' => $healthCheck->checked_on->toDateString(),
            'type' => $healthCheck->type->value,
            'clearance' => $healthCheck->result->clearance(),
        ])->all();
    }

    /**
     * Reduce follow-ups to what HR may see: when they are due and whether they happened, without the medical reason.
     *
     * @param  Collection<int, FollowUp>  $followUps
     * @return array<int, array<string, mixed>>
     */
    private function followUpSchedule(Collection $followUps): array
    {
        return $followUps->map(fn (FollowUp $followUp): array => [
            'id' => $followUp->id,
            'due_on' => $followUp->due_on->toDateString(),
            'status' => $followUp->status->value,
            'completed_on' => $followUp->completed_on?->toDateString(),
            'is_overdue' => $followUp->is_overdue,
        ])->all();
    }

    /**
     * Get the companies the user may file employees under. HR is limited to their own.
     *
     * @return Collection<int, Company>
     */
    private function companyOptions(User $user): Collection
    {
        return Company::query()
            ->when(! $user->managesHealthRecords(), fn ($query) => $query->whereKey($user->company_id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
