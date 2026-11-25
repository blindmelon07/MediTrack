<?php

namespace App\Http\Controllers;

use App\Enums\FollowUpStatus;
use App\Enums\HealthCheckResult;
use App\Enums\HealthCheckType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FollowUp;
use App\Models\HealthCheck;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The number of rows shown in each "needs attention" list.
     */
    private const LIST_LIMIT = 8;

    /**
     * Show what needs attention, tailored to the user's role: the full picture for medical staff,
     * a company-scoped clearance view for HR, and their own schedule for employees.
     */
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $shared = [
            'healthCheckTypes' => HealthCheckType::options(),
            'healthCheckResults' => HealthCheckResult::options(),
            'settings' => [
                'annualIntervalMonths' => (int) config('meditrack.annual_check.interval_months'),
                'annualReminderDays' => (int) config('meditrack.annual_check.reminder_days'),
                'followUpLookaheadDays' => $this->lookaheadDays(),
            ],
        ];

        if ($user->managesHealthRecords()) {
            return Inertia::render('dashboard', [...$shared, 'view' => 'medical', ...$this->medicalOverview($user)]);
        }

        if ($user->can('viewAny', Employee::class)) {
            return Inertia::render('dashboard', [...$shared, 'view' => 'hr', ...$this->hrOverview($user)]);
        }

        return Inertia::render('dashboard', [...$shared, 'view' => 'employee', 'ownRecord' => $this->ownRecord($user)]);
    }

    /**
     * Build the overview for medical staff and administrators, across every company.
     *
     * @return array<string, mixed>
     */
    private function medicalOverview(User $user): array
    {
        return [
            'stats' => [
                ...$this->scheduleStats($user),
                'awaitingResults' => HealthCheck::query()->where('result', HealthCheckResult::Pending)->count(),
            ],
            'overdueFollowUps' => $this->followUpList($user, overdue: true, withMedicalDetails: true),
            'upcomingFollowUps' => $this->followUpList($user, overdue: false, withMedicalDetails: true),
            'annualChecksDue' => $this->annualChecksDue($user),
            'awaitingResults' => HealthCheck::query()
                ->where('result', HealthCheckResult::Pending)
                ->with('employee:id,employee_number,first_name,last_name')
                ->oldest('checked_on')
                ->limit(self::LIST_LIMIT)
                ->get(['id', 'employee_id', 'type', 'checked_on']),
            'companies' => Company::query()
                ->withCount([
                    'employees as active_employees_count' => fn ($query) => $query->active(),
                    'employees as annual_overdue_count' => fn ($query) => $query->overdueForAnnualCheck(),
                    'followUps as overdue_follow_ups_count' => fn ($query) => $query->overdue(),
                ])
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }

    /**
     * Build the overview for HR: their own company only, without diagnoses or follow-up reasons.
     *
     * @return array<string, mixed>
     */
    private function hrOverview(User $user): array
    {
        return [
            'companyName' => $user->company?->name,
            'stats' => $this->scheduleStats($user),
            'overdueFollowUps' => $this->followUpList($user, overdue: true, withMedicalDetails: false),
            'upcomingFollowUps' => $this->followUpList($user, overdue: false, withMedicalDetails: false),
            'annualChecksDue' => $this->annualChecksDue($user),
        ];
    }

    /**
     * Build an employee's view of their own schedule.
     *
     * @return array<string, mixed>|null
     */
    private function ownRecord(User $user): ?array
    {
        $employee = $user->employee()->withLastAnnualCheck()->first();

        if ($employee === null) {
            return null;
        }

        return [
            'employee' => $employee->only(['id', 'full_name', 'employee_number']),
            'annualCheck' => $employee->annualCheckSummary(),
            'latestCheck' => $employee->healthChecks()->latest('checked_on')->first(['id', 'type', 'checked_on', 'result']),
            'openFollowUps' => $employee->followUps()
                ->where('status', FollowUpStatus::Pending)
                ->orderBy('due_on')
                ->get(['id', 'reason', 'due_on', 'status']),
        ];
    }

    /**
     * Count overdue and upcoming work among the employees the user may see.
     *
     * @return array{overdueFollowUps: int, upcomingFollowUps: int, annualChecksOverdue: int, annualChecksDueSoon: int, activeEmployees: int}
     */
    private function scheduleStats(User $user): array
    {
        $annualChecksOverdue = Employee::query()->visibleTo($user)->overdueForAnnualCheck()->count();

        return [
            'overdueFollowUps' => FollowUp::query()->forEmployeesVisibleTo($user)->overdue()->count(),
            'upcomingFollowUps' => FollowUp::query()->forEmployeesVisibleTo($user)->dueWithinDays($this->lookaheadDays())->count(),
            'annualChecksOverdue' => $annualChecksOverdue,
            'annualChecksDueSoon' => Employee::query()->visibleTo($user)->dueForAnnualCheck()->count() - $annualChecksOverdue,
            'activeEmployees' => Employee::query()->visibleTo($user)->active()->count(),
        ];
    }

    /**
     * List overdue or upcoming follow-ups, soonest first. HR gets dates and status only.
     *
     * @return array<int, array<string, mixed>>
     */
    private function followUpList(User $user, bool $overdue, bool $withMedicalDetails): array
    {
        return FollowUp::query()
            ->forEmployeesVisibleTo($user)
            ->when(
                $overdue,
                fn (Builder $query) => $query->overdue(),
                fn (Builder $query) => $query->dueWithinDays($this->lookaheadDays()),
            )
            ->with(['employee:id,employee_number,first_name,last_name,company_id', 'employee.company:id,name', 'assignee:id,name'])
            ->orderBy('due_on')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (FollowUp $followUp): array => [
                'id' => $followUp->id,
                'due_on' => $followUp->due_on->toDateString(),
                'is_overdue' => $followUp->is_overdue,
                'employee' => [
                    'id' => $followUp->employee->id,
                    'full_name' => $followUp->employee->full_name,
                    'employee_number' => $followUp->employee->employee_number,
                    'company' => $followUp->employee->company?->name,
                ],
                ...($withMedicalDetails ? [
                    'reason' => $followUp->reason,
                    'assignee' => $followUp->assignee?->name,
                ] : []),
            ])
            ->all();
    }

    /**
     * List active employees whose annual exam is overdue or due soon, longest-waiting first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function annualChecksDue(User $user): array
    {
        return Employee::query()
            ->visibleTo($user)
            ->dueForAnnualCheck()
            ->withLastAnnualCheck()
            ->with('company:id,name')
            ->orderBy('last_annual_check_on')
            ->orderBy('last_name')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'company' => $employee->company?->name,
                'annual_check' => $employee->annualCheckSummary(),
            ])
            ->all();
    }

    /**
     * Get how many days ahead counts as "upcoming" for follow-ups.
     */
    private function lookaheadDays(): int
    {
        return (int) config('meditrack.follow_up_lookahead_days');
    }
}
