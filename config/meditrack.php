<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Annual Health Check Schedule
    |--------------------------------------------------------------------------
    |
    | Every active employee needs a full exam (an annual physical, or their
    | pre-employment exam when newly hired) once per interval. An employee is
    | "due soon" from `reminder_days` before that date and "overdue" after it.
    |
    */

    'annual_check' => [
        'interval_months' => (int) env('ANNUAL_CHECK_INTERVAL_MONTHS', 12),
        'reminder_days' => (int) env('ANNUAL_CHECK_REMINDER_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Upcoming Follow-ups
    |--------------------------------------------------------------------------
    |
    | How many days ahead the dashboard looks when listing follow-ups that are
    | coming up soon.
    |
    */

    'follow_up_lookahead_days' => (int) env('FOLLOW_UP_LOOKAHEAD_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Reminders
    |--------------------------------------------------------------------------
    |
    | Employees get an SMS (and an email when they have an address) this many
    | days before a follow-up or annual check is due, again on the due date,
    | and then every `overdue_repeat_days` days while it stays overdue.
    | Reminders go out daily at `send_at` (Manila time); HR digests go out
    | weekly on `hr_digest_day`.
    |
    */

    'reminders' => [
        'days_before' => (int) env('REMINDER_DAYS_BEFORE', 3),
        'overdue_repeat_days' => (int) env('REMINDER_OVERDUE_REPEAT_DAYS', 7),
        'send_at' => env('REMINDER_SEND_AT', '07:00'),
        'hr_digest_day' => env('REMINDER_HR_DIGEST_DAY', 'monday'),
    ],

];
