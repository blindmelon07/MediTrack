<?php

namespace App\Enums;

enum ReminderType: string
{
    case FollowUpDueSoon = 'follow_up_due_soon';
    case FollowUpDueToday = 'follow_up_due_today';
    case FollowUpOverdue = 'follow_up_overdue';
    case AnnualCheckDueSoon = 'annual_check_due_soon';
    case AnnualCheckDueToday = 'annual_check_due_today';
    case AnnualCheckOverdue = 'annual_check_overdue';
    case AppointmentTomorrow = 'appointment_tomorrow';
    case StaffDailyDigest = 'staff_daily_digest';
    case HrWeeklyDigest = 'hr_weekly_digest';

    /**
     * Pick the employee reminder for something due on the given date, or null when no reminder goes out today.
     * Reminders go out a set number of days before, on the day, and then every few days while overdue.
     *
     * @param  'follow_up'|'annual_check'  $subject
     */
    public static function forDueDate(string $subject, \DateTimeInterface $dueOn): ?self
    {
        $daysUntilDue = (int) today()->diffInDays($dueOn, false);
        $daysBefore = (int) config('meditrack.reminders.days_before');
        $repeatEvery = max(1, (int) config('meditrack.reminders.overdue_repeat_days'));

        $stage = match (true) {
            $daysUntilDue === $daysBefore && $daysBefore > 0 => 'due_soon',
            $daysUntilDue === 0 => 'due_today',
            $daysUntilDue < 0 && -$daysUntilDue % $repeatEvery === 0 => 'overdue',
            default => null,
        };

        return $stage === null ? null : self::from("{$subject}_{$stage}");
    }
}
