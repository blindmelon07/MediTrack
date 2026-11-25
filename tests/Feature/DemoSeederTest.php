<?php

use App\Enums\Role;
use App\Models\Employee;
use App\Models\FollowUp;
use App\Models\User;
use Database\Seeders\DemoSeeder;

test('loads accounts for every role and employees in every situation', function () {
    $this->travelTo('2026-11-26 09:00:00');

    $this->seed(DemoSeeder::class);

    expect(User::query()->where('role', Role::Hr)->pluck('email')->sort()->values()->all())
        ->toBe(['hr.gghi@meditrack.test', 'hr.gsac@meditrack.test', 'hr.tripple2@meditrack.test']);
    expect(User::firstWhere('email', 'employee@meditrack.test')->employee->employee_number)->toBe('GGHI-0005');
    expect(Employee::count())->toBe(30);
    expect(Employee::query()->overdueForAnnualCheck()->count())->toBe(6);
    expect(Employee::query()->dueForAnnualCheck()->count())->toBe(9);
    expect(FollowUp::query()->overdue()->count())->toBe(3);
    expect(FollowUp::query()->dueWithinDays(0)->count())->toBe(3);
    expect(Employee::query()->whereNotNull('phone')->exists())->toBeFalse();
});

test('does nothing when the demo data is already loaded', function () {
    $this->seed(DemoSeeder::class);

    $this->seed(DemoSeeder::class);

    expect(Employee::count())->toBe(30);
    expect(User::count())->toBe(7);
});
