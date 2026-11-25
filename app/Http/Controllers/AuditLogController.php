<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * List audit entries, newest first, filtered by person, action, employee record, and date.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AuditLog::class);

        $filters = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'integer'],
            'action' => ['nullable', Rule::enum(AuditAction::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $logs = AuditLog::query()
            ->with(['user:id,name,role', 'employee:id,employee_number,first_name,last_name'])
            ->when($filters['user_id'] ?? null, fn ($query, int $userId) => $query->where('user_id', $userId))
            ->when($filters['employee_id'] ?? null, fn ($query, int $employeeId) => $query->where('employee_id', $employeeId))
            ->when($filters['action'] ?? null, fn ($query, string $action) => $query->where('action', $action))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->latest('created_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('audit-logs/index', [
            'logs' => $logs,
            'filters' => [
                'user_id' => $filters['user_id'] ?? null,
                'employee_id' => $filters['employee_id'] ?? null,
                'action' => $filters['action'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
            'filteredEmployee' => isset($filters['employee_id'])
                ? Employee::query()->find($filters['employee_id'], ['id', 'employee_number', 'first_name', 'last_name'])
                : null,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'actions' => AuditAction::options(),
        ]);
    }
}
