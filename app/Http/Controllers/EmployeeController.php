<?php

namespace App\Http\Controllers;

use App\Enums\FollowUpStatus;
use App\Enums\HealthCheckResult;
use App\Enums\HealthCheckType;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    /**
     * List employees, filtered by search term and employment status.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status', 'active')->toString();

        $employees = Employee::query()
            ->search($search)
            ->when($status === 'active', fn ($query) => $query->active())
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->with('latestHealthCheck')
            ->withCount(['followUps as pending_follow_ups_count' => fn ($query) => $query->where('status', FollowUpStatus::Pending)])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('employees/index', [
            'employees' => $employees,
            'filters' => ['search' => $search, 'status' => $status],
            'healthCheckTypes' => HealthCheckType::options(),
            'healthCheckResults' => HealthCheckResult::options(),
        ]);
    }

    /**
     * Show the form for adding an employee.
     */
    public function create(): Response
    {
        return Inertia::render('employees/create');
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
     * Show the employee's profile with their full health history.
     */
    public function show(Employee $employee): Response
    {
        return Inertia::render('employees/show', [
            'employee' => $employee,
            'healthChecks' => $employee->healthChecks()
                ->withCount('attachments')
                ->orderByDesc('checked_on')
                ->get(),
            'followUps' => $employee->followUps()
                ->with('assignee:id,name')
                ->orderByDesc('due_on')
                ->get(),
            'healthCheckTypes' => HealthCheckType::options(),
            'healthCheckResults' => HealthCheckResult::options(),
            'followUpStatuses' => FollowUpStatus::options(),
        ]);
    }

    /**
     * Show the form for editing an employee.
     */
    public function edit(Employee $employee): Response
    {
        return Inertia::render('employees/edit', [
            'employee' => $employee,
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
}
