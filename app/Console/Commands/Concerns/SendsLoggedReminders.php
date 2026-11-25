<?php

namespace App\Console\Commands\Concerns;

use App\Enums\ReminderType;
use App\Models\Employee;
use App\Models\ReminderLog;
use App\Models\User;
use App\Notifications\EmployeeReminder;
use App\Notifications\HrWeeklyDigest;
use App\Notifications\StaffDailyDigest;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Sends a notification at most once per subject per day, recording it in reminder_logs.
 * A failed send releases the claim so the next run retries it.
 *
 * @mixin Command
 */
trait SendsLoggedReminders
{
    /**
     * @var array{sent: int, skipped: int, failed: int}
     */
    protected array $tally = ['sent' => 0, 'skipped' => 0, 'failed' => 0];

    /**
     * Send the reminder unless it already went out today or the recipient has no way to receive it.
     */
    protected function sendReminder(
        Employee|User $notifiable,
        EmployeeReminder|StaffDailyDigest|HrWeeklyDigest $notification,
        ReminderType $type,
        Model $subject,
        string $description,
    ): void {
        $channels = $notification->via($notifiable);

        if ($channels === []) {
            $this->tally['skipped']++;
            $this->components->warn("Skipped {$description}: no mobile number or email on file.");

            return;
        }

        if ($this->option('dry-run')) {
            $this->tally['sent']++;
            $this->components->twoColumnDetail($description, 'would send via '.implode(', ', array_map(class_basename(...), $channels)));

            return;
        }

        $log = ReminderLog::claim($type, $subject, array_map(class_basename(...), $channels));

        if ($log === null) {
            $this->tally['skipped']++;

            return;
        }

        try {
            $notifiable->notify($notification);
            $this->tally['sent']++;
            $this->components->twoColumnDetail($description, '<fg=green>sent</>');
        } catch (Throwable $exception) {
            $log->delete();
            report($exception);
            $this->tally['failed']++;
            $this->components->twoColumnDetail($description, '<fg=red>failed: '.$exception->getMessage().'</>');
        }
    }

    /**
     * Print the totals and return the exit code: failure when any send failed.
     */
    protected function finish(): int
    {
        $this->newLine();
        $this->components->info(sprintf(
            '%s%d sent, %d skipped, %d failed.',
            $this->option('dry-run') ? '[Dry run] ' : '',
            $this->tally['sent'],
            $this->tally['skipped'],
            $this->tally['failed'],
        ));

        return $this->tally['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
