<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SendsLoggedReminders;
use App\Enums\ReminderType;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\FollowUp;
use App\Models\User;
use App\Notifications\HrWeeklyDigest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('app:send-hr-digest {--dry-run : List what would be sent without sending anything}')]
#[Description('Email each HR user the overdue annual checks and follow-ups for their company')]
class SendHrDigest extends Command
{
    use SendsLoggedReminders;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        User::query()
            ->where('role', Role::Hr)
            ->whereNotNull('company_id')
            ->whereNotNull('email_verified_at')
            ->with('company:id,name')
            ->lazyById()
            ->each(function (User $user): void {
                $annualChecksOverdue = Employee::query()
                    ->visibleTo($user)
                    ->overdueForAnnualCheck()
                    ->withLastAnnualCheck()
                    ->orderBy('last_annual_check_on')
                    ->orderBy('last_name')
                    ->get();

                $overdueFollowUps = FollowUp::query()
                    ->forEmployeesVisibleTo($user)
                    ->overdue()
                    ->whereHas('employee', fn (Builder $query) => $query->where('is_active', true))
                    ->with('employee:id,first_name,last_name')
                    ->orderBy('due_on')
                    ->get();

                if ($annualChecksOverdue->isEmpty() && $overdueFollowUps->isEmpty()) {
                    return;
                }

                $this->sendReminder(
                    $user,
                    new HrWeeklyDigest((string) $user->company?->name, $annualChecksOverdue, $overdueFollowUps),
                    ReminderType::HrWeeklyDigest,
                    $user,
                    "{$user->name} ({$user->company?->name}): {$annualChecksOverdue->count()} annual, {$overdueFollowUps->count()} follow-ups",
                );
            });

        return $this->finish();
    }
}
