<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Reminders run on clinic (Manila) time. On Windows, Task Scheduler must run
| `php artisan schedule:run` every minute for these to fire.
*/
Schedule::command('app:send-reminders')
    ->dailyAt(config('meditrack.reminders.send_at'))
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();

Schedule::command('app:send-hr-digest')
    ->weeklyOn(
        CarbonImmutable::parse(config('meditrack.reminders.hr_digest_day'))->dayOfWeek,
        config('meditrack.reminders.send_at'),
    )
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();
