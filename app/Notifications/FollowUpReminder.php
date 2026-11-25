<?php

namespace App\Notifications;

use App\Enums\ReminderType;
use App\Models\FollowUp;

class FollowUpReminder extends EmployeeReminder
{
    public function __construct(public FollowUp $followUp, public ReminderType $type) {}

    /**
     * Get the email subject line.
     */
    protected function subject(): string
    {
        return match ($this->type) {
            ReminderType::FollowUpDueToday => 'Your clinic follow-up is due today',
            ReminderType::FollowUpOverdue => 'Your clinic follow-up is overdue',
            default => 'Upcoming clinic follow-up',
        };
    }

    /**
     * Get the reminder sentence that follows the greeting.
     */
    protected function body(): string
    {
        $dueOn = $this->followUp->due_on->format('M j, Y');

        return match ($this->type) {
            ReminderType::FollowUpDueToday => "your clinic follow-up is due today, {$dueOn}. Please visit the clinic today.",
            ReminderType::FollowUpOverdue => "your clinic follow-up was due on {$dueOn}. Please visit the clinic as soon as possible.",
            default => "you have a clinic follow-up on {$dueOn}. Please visit the clinic on or before that day.",
        };
    }
}
