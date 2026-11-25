<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('creates a verified administrator', function () {
    $this->artisan('app:create-admin-user', [
        '--name' => 'Clinic Admin',
        '--email' => 'admin@example.com',
        '--password' => 'secret-password',
    ])->assertSuccessful();

    $user = User::sole();
    expect($user)
        ->name->toBe('Clinic Admin')
        ->role->toBe(Role::Admin)
        ->email_verified_at->not->toBeNull();
    expect(Hash::check('secret-password', $user->password))->toBeTrue();
});

test('promotes an existing account to administrator', function () {
    $user = User::factory()->hr(Company::factory()->create())->create(['email' => 'nurse@example.com']);

    $this->artisan('app:create-admin-user', ['--email' => 'nurse@example.com'])->assertSuccessful();

    expect($user->fresh())
        ->role->toBe(Role::Admin)
        ->company_id->toBeNull();
    $this->assertDatabaseCount('users', 1);
});

test('rejects an invalid email address', function () {
    $this->artisan('app:create-admin-user', [
        '--name' => 'Clinic Admin',
        '--email' => 'not-an-email',
        '--password' => 'secret-password',
    ])->expectsOutputToContain('must be a valid email address')->assertFailed();

    $this->assertDatabaseCount('users', 0);
});
