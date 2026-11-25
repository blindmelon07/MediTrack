<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\HealthCheck;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

describe('recording', function () {
    test('records who created a health check and whose record it is', function () {
        $doctor = User::factory()->medicalStaff()->create();
        $employee = Employee::factory()->create();
        $this->actingAs($doctor);

        $healthCheck = HealthCheck::factory()->for($employee)->create();

        $log = AuditLog::query()->where('subject_type', $healthCheck->getMorphClass())->sole();
        expect($log)
            ->action->toBe(AuditAction::Created)
            ->user_id->toBe($doctor->id)
            ->employee_id->toBe($employee->id)
            ->subject_id->toBe($healthCheck->id);
    });

    test('records which fields changed but never the medical values', function () {
        $healthCheck = HealthCheck::factory()->create();
        $this->actingAs(User::factory()->medicalStaff()->create());

        $healthCheck->update(['diagnosis' => 'Pulmonary tuberculosis', 'result' => 'abnormal']);

        $log = AuditLog::query()->where('action', AuditAction::Updated)->sole();
        expect($log->changed_fields)->toEqualCanonicalizing(['diagnosis', 'result']);
        $this->assertDatabaseMissing('audit_logs', ['description' => 'Pulmonary tuberculosis']);
        expect(AuditLog::query()->where('description', 'like', '%tuberculosis%')->exists())->toBeFalse();
        expect(json_encode(AuditLog::all()))->not->toContain('tuberculosis');
    });

    test('does not record a login refreshing the remember token', function () {
        $user = User::factory()->create();
        AuditLog::query()->delete();

        $user->forceFill(['remember_token' => 'new-token'])->save();

        $this->assertDatabaseCount('audit_logs', 0);
    });

    test('records HR viewing an employee as a clearance summary view', function () {
        $company = Company::factory()->create();
        $hr = User::factory()->hr($company)->create();
        $employee = Employee::factory()->for($company)->create();

        $this->actingAs($hr)->get(route('employees.show', $employee))->assertOk();

        expect(AuditLog::query()->where('action', AuditAction::Viewed)->sole())
            ->user_id->toBe($hr->id)
            ->employee_id->toBe($employee->id)
            ->description->toStartWith('Viewed clearance summary of employee');
    });

    test('records medical staff viewing a health check', function () {
        $doctor = User::factory()->medicalStaff()->create();
        $healthCheck = HealthCheck::factory()->create();

        $this->actingAs($doctor)->get(route('health-checks.show', $healthCheck))->assertOk();

        expect(AuditLog::query()->where('action', AuditAction::Viewed)->sole())
            ->user_id->toBe($doctor->id)
            ->employee_id->toBe($healthCheck->employee_id);
    });

    test('records lab result downloads', function () {
        Storage::fake('local');
        $doctor = User::factory()->medicalStaff()->create();
        $attachment = HealthCheck::factory()->create()
            ->storeAttachment(UploadedFile::fake()->create('cbc.pdf', 10, 'application/pdf'));

        $this->actingAs($doctor)->get(route('attachments.show', $attachment))->assertOk();

        expect(AuditLog::query()->where('action', AuditAction::Downloaded)->sole())
            ->user_id->toBe($doctor->id)
            ->employee_id->toBe($attachment->healthCheck->employee_id)
            ->description->toContain('cbc.pdf');
    });

    test('records changes made without a signed-in user as the system', function () {
        Employee::factory()->create();

        expect(AuditLog::query()->where('action', AuditAction::Created)->first()->user_id)->toBeNull();
    });
});

describe('reviewing', function () {
    test('lets administrators filter the log to one employee\'s record', function () {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create();
        Employee::factory()->create();

        $response = $this->actingAs($admin)->get(route('audit-logs.index', ['employee_id' => $employee->id]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('audit-logs/index')
            ->has('logs.data', 1)
            ->where('logs.data.0.employee.id', $employee->id)
            ->where('filteredEmployee.id', $employee->id));
    });

    test('forbids everyone except administrators', function (string $role) {
        $response = $this->actingAs(User::factory()->create(['role' => $role]))->get(route('audit-logs.index'));

        $response->assertForbidden();
    })->with(['medical staff' => 'medical_staff', 'HR' => 'hr', 'employee' => 'employee']);
});
