<?php

use App\Models\Employee;
use App\Models\HealthCheck;
use App\Models\HealthCheckAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(HealthCheckAttachment::DISK);
});

test('guests cannot download attachments', function () {
    $attachment = HealthCheck::factory()->create()
        ->storeAttachment(UploadedFile::fake()->create('cbc.pdf', 10, 'application/pdf'));

    $response = $this->get(route('attachments.show', $attachment));

    $response->assertRedirect(route('login'));
});

test('forbids HR from downloading lab results', function () {
    $user = User::factory()->hr()->create();
    $attachment = HealthCheck::factory()
        ->for(Employee::factory()->state(['company_id' => $user->company_id]))
        ->create()
        ->storeAttachment(UploadedFile::fake()->create('cbc.pdf', 10, 'application/pdf'));

    $response = $this->actingAs($user)->get(route('attachments.show', $attachment));

    $response->assertForbidden();
});

test('lets an employee download their own lab results', function () {
    $user = User::factory()->create();
    $attachment = HealthCheck::factory()
        ->for(Employee::factory()->state(['user_id' => $user->id]))
        ->create()
        ->storeAttachment(UploadedFile::fake()->create('cbc.pdf', 10, 'application/pdf'));

    $response = $this->actingAs($user)->get(route('attachments.show', $attachment));

    $response->assertOk()->assertDownload('cbc.pdf');
});

test('forbids an employee from uploading to their own health check', function () {
    $user = User::factory()->create();
    $healthCheck = HealthCheck::factory()->for(Employee::factory()->state(['user_id' => $user->id]))->create();

    $response = $this->actingAs($user)->post(route('health-checks.attachments.store', $healthCheck), [
        'attachments' => [UploadedFile::fake()->create('cbc.pdf', 10, 'application/pdf')],
    ]);

    $response->assertForbidden();
    $this->assertDatabaseCount('health_check_attachments', 0);
});

test('forbids an employee from deleting their own lab results', function () {
    $user = User::factory()->create();
    $attachment = HealthCheck::factory()
        ->for(Employee::factory()->state(['user_id' => $user->id]))
        ->create()
        ->storeAttachment(UploadedFile::fake()->create('cbc.pdf', 10, 'application/pdf'));

    $response = $this->actingAs($user)->delete(route('attachments.destroy', $attachment));

    $response->assertForbidden();
    $this->assertModelExists($attachment);
});

test('uploads files to a health check', function () {
    $healthCheck = HealthCheck::factory()->create();

    $response = $this->actingAs(User::factory()->medicalStaff()->create())->post(route('health-checks.attachments.store', $healthCheck), [
        'attachments' => [
            UploadedFile::fake()->create('cbc.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->image('ecg.png'),
        ],
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('health-checks.show', $healthCheck));
    expect($healthCheck->attachments()->pluck('original_name')->sort()->values()->all())->toBe(['cbc.pdf', 'ecg.png']);
});

test('rejects file types other than PDF, JPG, and PNG', function () {
    $healthCheck = HealthCheck::factory()->create();

    $response = $this->actingAs(User::factory()->medicalStaff()->create())->post(route('health-checks.attachments.store', $healthCheck), [
        'attachments' => [UploadedFile::fake()->create('notes.exe', 10, 'application/x-msdownload')],
    ]);

    $response->assertSessionHasErrors(['attachments.0' => 'Attachments must be PDF, JPG, or PNG files.']);
    $this->assertDatabaseCount('health_check_attachments', 0);
});

test('requires at least one file', function () {
    $healthCheck = HealthCheck::factory()->create();

    $response = $this->actingAs(User::factory()->medicalStaff()->create())
        ->post(route('health-checks.attachments.store', $healthCheck), []);

    $response->assertSessionHasErrors(['attachments' => 'Choose at least one file to upload.']);
});

test('downloads an attachment under its original name', function () {
    $attachment = HealthCheck::factory()->create()
        ->storeAttachment(UploadedFile::fake()->create('cbc.pdf', 10, 'application/pdf'));

    $response = $this->actingAs(User::factory()->medicalStaff()->create())->get(route('attachments.show', $attachment));

    $response->assertOk()->assertDownload('cbc.pdf');
});

test('deletes an attachment and its stored file', function () {
    $attachment = HealthCheck::factory()->create()
        ->storeAttachment(UploadedFile::fake()->create('cbc.pdf', 10, 'application/pdf'));

    $response = $this->actingAs(User::factory()->medicalStaff()->create())->delete(route('attachments.destroy', $attachment));

    $response->assertRedirect(route('health-checks.show', $attachment->health_check_id));
    $this->assertModelMissing($attachment);
    Storage::disk(HealthCheckAttachment::DISK)->assertMissing($attachment->path);
});
