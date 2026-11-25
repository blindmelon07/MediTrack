<?php

use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\HealthCheckAttachmentController;
use App\Http\Controllers\HealthCheckController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::resource('employees', EmployeeController::class)->except('destroy');
    Route::resource('health-checks', HealthCheckController::class);
    Route::resource('follow-ups', FollowUpController::class)->except('show');
    Route::resource('health-checks.attachments', HealthCheckAttachmentController::class)
        ->only(['store', 'show', 'destroy'])
        ->shallow();
});

require __DIR__.'/settings.php';
