<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeImportController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\HealthCheckAiSummaryController;
use App\Http\Controllers\HealthCheckAttachmentController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\HealthCheckFollowUpController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Declared before the resource so "import" is not read as an employee ID.
    Route::get('employees/import', [EmployeeImportController::class, 'create'])->name('employees.import.create');
    Route::get('employees/import/template', [EmployeeImportController::class, 'template'])->name('employees.import.template');
    Route::post('employees/import', [EmployeeImportController::class, 'store'])->name('employees.import.store');
    Route::resource('employees', EmployeeController::class)->except('destroy');
    Route::resource('health-checks', HealthCheckController::class);
    Route::post('health-checks/{health_check}/ai-summary', HealthCheckAiSummaryController::class)
        ->middleware('throttle:20,1')
        ->name('health-checks.ai-summary');
    Route::post('health-checks/{health_check}/follow-ups', [HealthCheckFollowUpController::class, 'store'])
        ->name('health-checks.follow-ups.store');
    Route::resource('follow-ups', FollowUpController::class)->except('show');
    Route::resource('health-checks.attachments', HealthCheckAttachmentController::class)
        ->only(['store', 'show', 'destroy'])
        ->shallow();
    Route::resource('appointments', AppointmentController::class)->only(['index', 'create', 'store', 'update']);
    Route::resource('users', UserController::class)->except('show');
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});

require __DIR__.'/settings.php';
