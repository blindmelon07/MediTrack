<?php

namespace App\Notifications;

use App\Models\FollowUp;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The morning email to a doctor or nurse listing follow-ups that are overdue or due today.
 */
class StaffDailyDigest extends Notification
{
    /**
     * The most follow-ups listed in the email body; the rest are summarized with a link.
     */
    private const MAX_LISTED = 30;

    /**
     * @param  Collection<int, FollowUp>  $followUps  overdue and due-today follow-ups, with employee and company loaded
     */
    public function __construct(public Collection $followUps) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $overdueCount = $this->followUps->filter->is_overdue->count();
        $dueTodayCount = $this->followUps->count() - $overdueCount;

        $mail = (new MailMessage)
            ->subject("Follow-ups needing attention: {$overdueCount} overdue, {$dueTodayCount} due today")
            ->greeting("Good morning, {$notifiable->name}")
            ->line('These follow-ups are assigned to you or not yet assigned:');

        foreach ($this->followUps->take(self::MAX_LISTED) as $followUp) {
            $when = $followUp->is_overdue
                ? 'OVERDUE since '.$followUp->due_on->format('M j')
                : 'due today';
            $company = $followUp->employee->company?->name;

            $mail->line("- {$followUp->employee->full_name}".($company ? " ({$company})" : '')." - {$followUp->reason} - {$when}");
        }

        if ($this->followUps->count() > self::MAX_LISTED) {
            $mail->line('...and '.($this->followUps->count() - self::MAX_LISTED).' more.');
        }

        return $mail->action('Open overdue follow-ups', route('follow-ups.index', ['status' => 'overdue']));
    }
}
