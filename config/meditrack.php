<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | The sidebar shows the app name (APP_NAME) with this tagline, and the
    | office line at the bottom. The office line also heads printed records.
    |
    */

    'branding' => [
        'tagline' => env('BRAND_TAGLINE', 'Employee Health Records'),
        'office' => env('BRAND_OFFICE', 'Company Clinic'),
        'office_address' => env('BRAND_OFFICE_ADDRESS'),
        // Full logo (heads printed records) and square mark (sidebar, login, browser tab), under public/.
        'logo' => env('BRAND_LOGO', '/images/gsac-logo.png'),
        'mark' => env('BRAND_MARK', '/images/gsac-mark.png'),
    ],

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

    /*
    |--------------------------------------------------------------------------
    | Clinic Appointments
    |--------------------------------------------------------------------------
    |
    | When the clinic sees patients. Appointments start on the slot grid from
    | `opens_at` to `closes_at`, skipping the break, with `patients_per_slot`
    | people per slot. Bookings can be made up to `booking_window_days` ahead.
    |
    */

    'clinic' => [
        'days' => array_map('trim', explode(',', (string) env('CLINIC_DAYS', 'monday,tuesday,wednesday,thursday,friday,saturday,sunday'))),
        'opens_at' => env('CLINIC_OPENS_AT', '08:00'),
        'closes_at' => env('CLINIC_CLOSES_AT', '17:00'),
        'break_starts_at' => env('CLINIC_BREAK_STARTS_AT', '12:00'),
        'break_ends_at' => env('CLINIC_BREAK_ENDS_AT', '13:00'),
        'slot_minutes' => (int) env('CLINIC_SLOT_MINUTES', 30),
        'patients_per_slot' => (int) env('CLINIC_PATIENTS_PER_SLOT', 1),
        'booking_window_days' => (int) env('CLINIC_BOOKING_WINDOW_DAYS', 60),
    ],

    'reminders' => [
        'days_before' => (int) env('REMINDER_DAYS_BEFORE', 3),
        'overdue_repeat_days' => (int) env('REMINDER_OVERDUE_REPEAT_DAYS', 7),
        'send_at' => env('REMINDER_SEND_AT', '07:00'),
        'hr_digest_day' => env('REMINDER_HR_DIGEST_DAY', 'monday'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Assistance
    |--------------------------------------------------------------------------
    |
    | Claude drafts summaries and follow-ups for medical staff to review. It is
    | off until AI_ENABLED is true and ANTHROPIC_API_KEY is set. Names and
    | contact details are removed before anything is sent, except for uploaded
    | lab result pages, which are sent as they are.
    |
    */

    'ai' => [
        'enabled' => (bool) env('AI_ENABLED', false),
        'model' => env('AI_MODEL', 'claude-opus-5-5'),
        'effort' => env('AI_EFFORT', 'medium'),
    ],

];
