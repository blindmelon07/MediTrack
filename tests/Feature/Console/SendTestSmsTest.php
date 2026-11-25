<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('sends a test message and prints the UniSMS reference', function () {
    config(['services.unisms.secret_key' => 'sk_test_123', 'services.unisms.sender_id' => 'MEDITRACK']);
    Http::fake(['unismsapi.com/api/sms' => Http::response(['message' => ['reference_id' => 'msg_test']], 201)]);

    $this->artisan('app:send-test-sms', ['number' => '0917 123 4567'])
        ->expectsOutputToContain('Sent to +639171234567. UniSMS reference: msg_test')
        ->assertSuccessful();
});

test('explains that the message was only logged when no key is set', function () {
    config(['services.unisms.secret_key' => null]);

    $this->artisan('app:send-test-sms', ['number' => '09171234567'])
        ->expectsOutputToContain('UNISMS_SECRET_KEY is not set')
        ->assertSuccessful();

    Http::assertNothingSent();
});

test('shows the UniSMS error when the message is rejected', function () {
    config(['services.unisms.secret_key' => 'bad_key', 'services.unisms.sender_id' => 'MEDITRACK']);
    Http::fake(['unismsapi.com/api/sms' => Http::response(['errors' => 'Unauthorized'], 401)]);

    $this->artisan('app:send-test-sms', ['number' => '09171234567'])
        ->expectsOutputToContain('UniSMS rejected the message (HTTP 401)')
        ->assertFailed();
});

test('rejects a number that is not a Philippine mobile number', function () {
    $this->artisan('app:send-test-sms', ['number' => '(02) 8123 4567'])
        ->expectsOutputToContain('Enter a Philippine mobile number')
        ->assertFailed();

    Http::assertNothingSent();
});
