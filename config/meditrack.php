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

];
