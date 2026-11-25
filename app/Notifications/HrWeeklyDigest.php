<?php

namespace App\Notifications;

use App\Models\Employee;
use App\Models\FollowUp;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The weekly email to HR listing their company's overdue annual checks and follow-ups.
 * Like everything HR sees, it names who and when, never the medical reason.
 */
class HrWeeklyDigest extends Notification
{
    /**
     * The most people listed per section; the rest are summarized with a link.
     */
    private const MAX_LISTED = 30;

    /**
     * @param  Collection<int, Employee>  $annualChecksOverdue  with last_annual_check_on loaded
     * @param  Collection<int, FollowUp>  $overdueFollowUps  with employee loaded
     */
    public function __construct(
        public string $companyName,
        public Collection $annualChecksOverdue,
        public Collection $overdueFollowUps,
    ) {}

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
        $mail = (new MailMessage)
            ->subject("{$this->companyName} weekly health compliance: {$this->annualChecksOverdue->count()} annual checks overdue, {$this->overdueFollowUps->count()} follow-ups overdue")
            ->greeting("Hi {$notifiable->name},")
            ->line("Here is this week's health compliance summary for {$this->companyName}. Please remind these employees to visit the clinic.");

        if ($this->annualChecksOverdue->isNotEmpty()) {
            $mail->line("**Annual health check overdue ({$this->annualChecksOverdue->count()})**");

            foreach ($this->annualChecksOverdue->take(self::MAX_LISTED) as $employee) {
                $summary = $employee->annualCheckSummary();
                $mail->line("- {$employee->full_name} - ".($summary['due_on'] === null ? 'no exam on record' : 'was due '.$this->formatDate($summary['due_on'])));
            }

            $this->addRemainder($mail, $this->annualChecksOverdue->count());
        }

        if ($this->overdueFollowUps->isNotEmpty()) {
            $mail->line("**Clinic follow-ups overdue ({$this->overdueFollowUps->count()})**");

            foreach ($this->overdueFollowUps->take(self::MAX_LISTED) as $followUp) {
                $mail->line("- {$followUp->employee->full_name} - was due ".$followUp->due_on->format('M j, Y'));
            }

            $this->addRemainder($mail, $this->overdueFollowUps->count());
        }

        return $mail
            ->action('Open the list', route('employees.index', ['annual' => 'overdue']))
            ->line('Medical details stay confidential with the clinic.');
    }

    /**
     * Mention how many people did not fit in the email.
     */
    private function addRemainder(MailMessage $mail, int $total): void
    {
        if ($total > self::MAX_LISTED) {
            $mail->line('...and '.($total - self::MAX_LISTED).' more.');
        }
    }

    /**
     * Format a Y-m-d date for the email.
     */
    private function formatDate(string $date): string
    {
        return CarbonImmutable::parse($date)->format('M j, Y');
    }
}
