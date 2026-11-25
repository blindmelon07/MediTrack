<?php

namespace App\Notifications;

use App\Models\Employee;
use App\Notifications\Channels\UniSmsChannel;
use App\Notifications\Messages\UniSmsMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A reminder sent to an employee by SMS (when they have a mobile number) and email (when they have an address).
 * The wording never includes the medical reason, because phones and inboxes are often shared.
 */
abstract class EmployeeReminder extends Notification
{
    /**
     * Get the email subject line.
     */
    abstract protected function subject(): string;

    /**
     * Get the reminder sentence that follows the greeting.
     */
    abstract protected function body(): string;

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof Employee) {
            return [];
        }

        return array_values(array_filter([
            filled($notifiable->phone) ? UniSmsChannel::class : null,
            filled($notifiable->email) ? 'mail' : null,
        ]));
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toUniSms(Employee $notifiable): UniSmsMessage
    {
        return new UniSmsMessage("Hi {$notifiable->first_name}, reminder from ".config('app.name').': '.$this->body());
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(Employee $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject())
            ->greeting("Hi {$notifiable->first_name},")
            ->line($this->body())
            ->line('If you have already been seen, please let the clinic know so we can update your record.');
    }
}
