<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SendsLoggedReminders;
use App\Enums\FollowUpStatus;
use App\Enums\ReminderType;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\FollowUp;
use App\Models\User;
use App\Notifications\AnnualCheckReminder;
use App\Notifications\FollowUpReminder;
use App\Notifications\StaffDailyDigest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('app:send-reminders {--dry-run : List what would be sent without sending anything}')]
#[Description('Text and email employees about upcoming and overdue follow-ups and annual checks, and email medical staff their daily list')]
class SendReminders extends Command
{
    use SendsLoggedReminders;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Follow-up reminders');
        $this->remindFollowUps();

        $this->components->info('Annual check reminders');
        $this->remindAnnualChecks();

        $this->components->info('Medical staff daily list');
        $this->sendStaffDigests();

        return $this->finish();
    }

    /**
     * Remind employees of pending follow-ups that are due soon, due today, or overdue.
     */
    private function remindFollowUps(): void
    {
        FollowUp::query()
            ->where('status', FollowUpStatus::Pending)
            ->whereDate('due_on', '<=', today()->addDays((int) config('meditrack.reminders.days_before')))
            ->whereHas('employee', fn (Builder $query) => $query->where('is_active', true))
            ->with('employee')
            ->lazyById()
            ->each(function (FollowUp $followUp): void {
                $employee = $followUp->employee;
                $type = ReminderType::forDueDate('follow_up', $followUp->due_on);

                if ($employee === null || $type === null) {
                    return;
                }

                $this->sendReminder(
                    $employee,
                    new FollowUpReminder($followUp, $type),
                    $type,
                    $followUp,
                    "{$employee->full_name}: follow-up due {$followUp->due_on->toDateString()}",
                );
            });
    }

    /**
     * Remind employees whose annual exam is due soon, due today, or overdue. Employees never examined
     * have no due date, so they appear in the HR digest instead.
     */
    private function remindAnnualChecks(): void
    {
        Employee::query()
            ->active()
            ->withLastAnnualCheck()
            ->lazyById()
            ->each(function (Employee $employee): void {
                $dueOn = $employee->nextAnnualCheckDueOn();
                $type = $dueOn === null ? null : ReminderType::forDueDate('annual_check', $dueOn);

                if ($type === null) {
                    return;
                }

                $this->sendReminder(
                    $employee,
                    new AnnualCheckReminder($dueOn, $type),
                    $type,
                    $employee,
                    "{$employee->full_name}: annual check due {$dueOn->toDateString()}",
                );
            });
    }

    /**
     * Email each doctor, nurse, and administrator the follow-ups assigned to them (or unassigned)
     * that are overdue or due today.
     */
    private function sendStaffDigests(): void
    {
        User::query()
            ->whereIn('role', [Role::Admin, Role::MedicalStaff])
            ->whereNotNull('email_verified_at')
            ->lazyById()
            ->each(function (User $user): void {
                $followUps = FollowUp::query()
                    ->where('status', FollowUpStatus::Pending)
                    ->whereDate('due_on', '<=', today())
                    ->where(fn (Builder $query) => $query->where('assigned_to', $user->id)->orWhereNull('assigned_to'))
                    ->whereHas('employee', fn (Builder $query) => $query->where('is_active', true))
                    ->with('employee.company:id,name')
                    ->orderBy('due_on')
                    ->get();

                if ($followUps->isEmpty()) {
                    return;
                }

                $this->sendReminder(
                    $user,
                    new StaffDailyDigest($followUps),
                    ReminderType::StaffDailyDigest,
                    $user,
                    "{$user->name}: {$followUps->count()} follow-ups",
                );
            });
    }
}
