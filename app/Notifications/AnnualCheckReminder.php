<?php

namespace App\Notifications;

use App\Enums\ReminderType;
use Carbon\CarbonInterface;

class AnnualCheckReminder extends EmployeeReminder
{
    public function __construct(public CarbonInterface $dueOn, public ReminderType $type) {}

    /**
     * Get the email subject line.
     */
    protected function subject(): string
    {
        return match ($this->type) {
            ReminderType::AnnualCheckDueToday => 'Your annual health check is due today',
            ReminderType::AnnualCheckOverdue => 'Your annual health check is overdue',
            default => 'Your annual health check is coming up',
        };
    }

    /**
     * Get the reminder sentence that follows the greeting.
     */
    protected function body(): string
    {
        $dueOn = $this->dueOn->format('M j, Y');

        return match ($this->type) {
            ReminderType::AnnualCheckDueToday => "your annual health check is due today, {$dueOn}. Please book your exam with the clinic.",
            ReminderType::AnnualCheckOverdue => "your annual health check was due on {$dueOn}. Please book your exam with the clinic as soon as possible.",
            default => "your annual health check is due on {$dueOn}. Please book your exam with the clinic.",
        };
    }
}
