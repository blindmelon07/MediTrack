<?php

namespace App\Http\Controllers;

use App\Enums\FollowUpStatus;
use App\Http\Requests\StoreFollowUpRequest;
use App\Http\Requests\UpdateFollowUpRequest;
use App\Models\Employee;
use App\Models\FollowUp;
use App\Models\HealthCheck;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FollowUpController extends Controller
{
    /**
     * List follow-ups, soonest due first, filtered by status and employee.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([...array_column(FollowUpStatus::cases(), 'value'), 'overdue', 'all'])],
        ]);

        $status = $filters['status'] ?? FollowUpStatus::Pending->value;

        $followUps = FollowUp::query()
            ->with(['employee:id,employee_number,first_name,last_name,department', 'assignee:id,name'])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->whereHas(
                'employee',
                fn ($query) => $query->search($search),
            ))
            ->when($status === 'overdue', fn ($query) => $query->overdue())
            ->when(! in_array($status, ['overdue', 'all'], true), fn ($query) => $query->where('status', $status))
            ->orderBy('due_on')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('follow-ups/index', [
            'followUps' => $followUps,
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $status],
            'followUpStatuses' => FollowUpStatus::options(),
            'overdueCount' => FollowUp::query()->overdue()->count(),
        ]);
    }

    /**
     * Show the form for scheduling a follow-up.
     */
    public function create(Request $request): Response
    {
        $healthCheck = $request->filled('health_check_id')
            ? HealthCheck::query()->find($request->integer('health_check_id'), ['id', 'employee_id', 'type', 'checked_on', 'recommendations'])
            : null;

        $selectedEmployeeId = $healthCheck->employee_id ?? ($request->integer('employee_id') ?: null);

        return Inertia::render('follow-ups/create', [
            'employees' => Employee::selectOptions($selectedEmployeeId),
            'selectedEmployeeId' => $selectedEmployeeId,
            'healthCheck' => $healthCheck,
            'staff' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Save a new follow-up.
     */
    public function store(StoreFollowUpRequest $request): RedirectResponse
    {
        $followUp = FollowUp::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Follow-up scheduled.')]);

        return to_route('employees.show', $followUp->employee_id);
    }

    /**
     * Show the form for updating a follow-up or recording its outcome.
     */
    public function edit(FollowUp $followUp): Response
    {
        $followUp->load([
            'employee:id,employee_number,first_name,last_name',
            'healthCheck:id,employee_id,type,checked_on',
        ]);

        return Inertia::render('follow-ups/edit', [
            'followUp' => $followUp,
            'employees' => Employee::selectOptions($followUp->employee_id),
            'staff' => User::query()->orderBy('name')->get(['id', 'name']),
            'followUpStatuses' => FollowUpStatus::options(),
        ]);
    }

    /**
     * Update a follow-up. Completing it records today's date unless another date is given.
     */
    public function update(UpdateFollowUpRequest $request, FollowUp $followUp): RedirectResponse
    {
        $attributes = $request->validated();

        $attributes['completed_on'] = $attributes['status'] === FollowUpStatus::Completed->value
            ? ($attributes['completed_on'] ?? today()->toDateString())
            : null;

        $followUp->update($attributes);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Follow-up updated.')]);

        return to_route('employees.show', $followUp->employee_id);
    }

    /**
     * Delete a follow-up.
     */
    public function destroy(FollowUp $followUp): RedirectResponse
    {
        $employeeId = $followUp->employee_id;

        $followUp->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Follow-up deleted.')]);

        return to_route('employees.show', $employeeId);
    }
}
