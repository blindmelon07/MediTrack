<?php

namespace App\Notifications;

use App\Models\Appointment;

/**
 * Tells an employee their clinic appointment was booked, cancelled, or is tomorrow.
 */
class AppointmentNotice extends EmployeeReminder
{
    public const BOOKED = 'booked';

    public const CANCELLED = 'cancelled';

    public const TOMORROW = 'tomorrow';

    /**
     * @param  self::BOOKED|self::CANCELLED|self::TOMORROW  $event
     */
    public function __construct(public Appointment $appointment, public string $event) {}

    /**
     * Get the email subject line.
     */
    protected function subject(): string
    {
        return match ($this->event) {
            self::CANCELLED => 'Your clinic appointment was cancelled',
            self::TOMORROW => 'Reminder: clinic appointment tomorrow',
            default => 'Your clinic appointment is booked',
        };
    }

    /**
     * Get the reminder sentence that follows the greeting.
     */
    protected function body(): string
    {
        $when = $this->appointment->starts_at->format('D, M j, Y \a\t g:i A');

        return match ($this->event) {
            self::CANCELLED => "your clinic appointment on {$when} was cancelled. Please book a new time if you still need to be seen.",
            self::TOMORROW => "you have a clinic appointment tomorrow, {$when}. Please arrive 10 minutes early.",
            default => "your clinic appointment is booked for {$when}. Please arrive 10 minutes early.",
        };
    }
}
