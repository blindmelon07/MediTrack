<?php

use App\Notifications\Channels\UniSmsChannel;
use App\Notifications\Messages\UniSmsMessage;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('sends the message to UniSMS with the secret key as the basic auth username', function () {
    config(['services.unisms.secret_key' => 'sk_test_123', 'services.unisms.sender_id' => 'MEDITRACK']);
    Http::fake(['unismsapi.com/api/sms' => Http::response(['message' => ['reference_id' => 'msg_abc', 'status' => 'pending']], 201)]);

    $referenceId = app(UniSmsChannel::class)->sendText('+639171234567', new UniSmsMessage('Hello from the clinic'));

    expect($referenceId)->toBe('msg_abc');
    Http::assertSent(fn (Request $request) => $request->url() === 'https://unismsapi.com/api/sms'
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_123:'))
        && $request->data() === [
            'recipient' => '+639171234567',
            'content' => 'Hello from the clinic',
            'sender_id' => 'MEDITRACK',
        ]);
});

test('logs the message instead of sending it when no secret key is configured', function () {
    config(['services.unisms.secret_key' => null]);
    Log::spy();

    $referenceId = app(UniSmsChannel::class)->sendText('+639171234567', new UniSmsMessage('Hello from the clinic'));

    expect($referenceId)->toBeNull();
    Http::assertNothingSent();
    Log::shouldHaveReceived('info')->once()->withArgs(
        fn (string $message, array $context) => $context === ['recipient' => '+639171234567', 'content' => 'Hello from the clinic'],
    );
});

test('refuses to send without a sender ID, which UniSMS requires', function () {
    config(['services.unisms.secret_key' => 'sk_test_123', 'services.unisms.sender_id' => null]);

    app(UniSmsChannel::class)->sendText('+639171234567', new UniSmsMessage('Hello from the clinic'));
})->throws(RuntimeException::class, 'UNISMS_SENDER_ID is not set');

test('throws when UniSMS rejects the message', function () {
    config(['services.unisms.secret_key' => 'sk_test_123', 'services.unisms.sender_id' => 'MEDITRACK']);
    Http::fake(['unismsapi.com/api/sms' => Http::response(['errors' => 'invalid recipient'], 422)]);

    app(UniSmsChannel::class)->sendText('+639171234567', new UniSmsMessage('Hello from the clinic'));
})->throws(RequestException::class);

test('converts accented letters and emoji to plain text and caps the length', function () {
    $message = new UniSmsMessage('Hi Peña, see you at the clinic 😊 '.str_repeat('x', 700));

    $content = $message->sanitizedContent();

    expect($content)->toStartWith('Hi Pena, see you at the clinic')
        ->and(mb_check_encoding($content, 'ASCII'))->toBeTrue()
        ->and(strlen($content))->toBe(UniSmsMessage::MAX_LENGTH);
});
