<?php

namespace App\Http\Controllers;

use App\Enums\FollowUpStatus;
use App\Enums\HealthCheckResult;
use App\Enums\HealthCheckType;
use App\Http\Requests\StoreHealthCheckRequest;
use App\Http\Requests\UpdateHealthCheckRequest;
use App\Models\Employee;
use App\Models\HealthCheck;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HealthCheckController extends Controller
{
    /**
     * List health checks, filtered by employee, type, and result.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', HealthCheck::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(HealthCheckType::class)],
            'result' => ['nullable', Rule::enum(HealthCheckResult::class)],
        ]);

        $healthChecks = HealthCheck::query()
            ->with('employee:id,employee_number,first_name,last_name,department')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->whereHas(
                'employee',
                fn ($query) => $query->search($search),
            ))
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['result'] ?? null, fn ($query, string $result) => $query->where('result', $result))
            ->orderByDesc('checked_on')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('health-checks/index', [
            'healthChecks' => $healthChecks,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'type' => $filters['type'] ?? '',
                'result' => $filters['result'] ?? '',
            ],
            'healthCheckTypes' => HealthCheckType::options(),
            'healthCheckResults' => HealthCheckResult::options(),
        ]);
    }

    /**
     * Show the form for recording a health check.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', HealthCheck::class);

        $selectedEmployeeId = $request->integer('employee_id') ?: null;

        return Inertia::render('health-checks/create', [
            'employees' => Employee::selectOptions($selectedEmployeeId),
            'selectedEmployeeId' => $selectedEmployeeId,
            'staff' => User::query()->orderBy('name')->get(['id', 'name']),
            'healthCheckTypes' => HealthCheckType::options(),
            'healthCheckResults' => HealthCheckResult::options(),
        ]);
    }

    /**
     * Save a health check with its attachments and optional follow-up.
     */
    public function store(StoreHealthCheckRequest $request): RedirectResponse
    {
        $healthCheck = DB::transaction(function () use ($request): HealthCheck {
            $healthCheck = new HealthCheck($request->safe()->except([
                'attachments',
                'schedule_follow_up',
                'follow_up_reason',
                'follow_up_due_on',
                'follow_up_assigned_to',
            ]));
            $healthCheck->recorded_by = $request->user()->id;
            $healthCheck->save();

            if ($request->boolean('schedule_follow_up')) {
                $healthCheck->followUps()->create([
                    'employee_id' => $healthCheck->employee_id,
                    'reason' => $request->validated('follow_up_reason'),
                    'due_on' => $request->validated('follow_up_due_on'),
                    'assigned_to' => $request->validated('follow_up_assigned_to'),
                    'status' => FollowUpStatus::Pending,
                ]);
            }

            return $healthCheck;
        });

        /** @var array<int, UploadedFile> $files */
        $files = $request->file('attachments', []);

        foreach ($files as $file) {
            $healthCheck->storeAttachment($file);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Health check recorded.')]);

        return to_route('health-checks.show', $healthCheck);
    }

    /**
     * Show a health check with its follow-ups and attachments.
     */
    public function show(HealthCheck $healthCheck): Response
    {
        Gate::authorize('view', $healthCheck);

        $healthCheck->load([
            'employee:id,employee_number,first_name,last_name,department,position',
            'recorder:id,name',
            'followUps' => fn ($query) => $query->with('assignee:id,name')->orderBy('due_on'),
            'attachments',
        ]);

        return Inertia::render('health-checks/show', [
            'healthCheck' => $healthCheck,
            'can' => ['update' => Gate::allows('update', $healthCheck)],
            'healthCheckTypes' => HealthCheckType::options(),
            'healthCheckResults' => HealthCheckResult::options(),
            'followUpStatuses' => FollowUpStatus::options(),
        ]);
    }

    /**
     * Show the form for editing a health check.
     */
    public function edit(HealthCheck $healthCheck): Response
    {
        Gate::authorize('update', $healthCheck);

        return Inertia::render('health-checks/edit', [
            'healthCheck' => $healthCheck,
            'employees' => Employee::selectOptions($healthCheck->employee_id),
            'healthCheckTypes' => HealthCheckType::options(),
            'healthCheckResults' => HealthCheckResult::options(),
        ]);
    }

    /**
     * Update a health check.
     */
    public function update(UpdateHealthCheckRequest $request, HealthCheck $healthCheck): RedirectResponse
    {
        $healthCheck->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Health check updated.')]);

        return to_route('health-checks.show', $healthCheck);
    }

    /**
     * Delete a health check and its attachments. Its follow-ups are kept but unlinked.
     */
    public function destroy(HealthCheck $healthCheck): RedirectResponse
    {
        Gate::authorize('delete', $healthCheck);

        $employeeId = $healthCheck->employee_id;

        $healthCheck->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Health check deleted.')]);

        return to_route('employees.show', $employeeId);
    }
}
