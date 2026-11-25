<?php

namespace App\Notifications\Channels;

use App\Notifications\Messages\UniSmsMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Sends notifications as SMS through UniSMS (https://unismsapi.com/docs).
 *
 * Notifications opt in by listing this class in via() and defining toUniSms().
 * Notifiables provide the number through routeNotificationForUnisms().
 */
class UniSmsChannel
{
    /**
     * Send the notification as a text message.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notifiable, 'routeNotificationFor') || ! method_exists($notification, 'toUniSms')) {
            return;
        }

        $recipient = $notifiable->routeNotificationFor('unisms', $notification);

        if (blank($recipient)) {
            return;
        }

        /** @var UniSmsMessage $message */
        $message = $notification->toUniSms($notifiable);

        $this->sendText($recipient, $message);
    }

    /**
     * Send one SMS and return the UniSMS reference ID, or null when SMS is not configured and the message was only logged.
     *
     * @throws RequestException when UniSMS rejects the message
     * @throws ConnectionException when UniSMS cannot be reached
     */
    public function sendText(string $recipient, UniSmsMessage $message): ?string
    {
        $content = $message->sanitizedContent();

        if (! self::isConfigured()) {
            Log::info('UniSMS is not configured, so this SMS was logged instead of sent.', [
                'recipient' => $recipient,
                'content' => $content,
            ]);

            return null;
        }

        $senderId = config('services.unisms.sender_id');

        if (blank($senderId)) {
            throw new RuntimeException('UNISMS_SENDER_ID is not set. UniSMS requires a sender ID for every message.');
        }

        $response = Http::baseUrl((string) config('services.unisms.base_url'))
            ->withBasicAuth((string) config('services.unisms.secret_key'), '')
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->retry(
                3,
                fn (int $attempt, Throwable $exception): int => $exception instanceof RequestException
                    ? (int) ($exception->response->header('x-retry-after-ms') ?: 1500)
                    : 1000,
                fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->status() === 429),
            )
            ->post('/sms', [
                'recipient' => $recipient,
                'content' => $content,
                'sender_id' => $senderId,
            ]);

        return $response->json('message.reference_id');
    }

    /**
     * Determine whether a UniSMS secret key has been configured.
     */
    public static function isConfigured(): bool
    {
        return filled(config('services.unisms.secret_key'));
    }
}
