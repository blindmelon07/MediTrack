<?php

namespace App\Console\Commands;

use App\Notifications\Channels\UniSmsChannel;
use App\Notifications\Messages\UniSmsMessage;
use App\Rules\PhilippineMobileNumber;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Throwable;

#[Signature('app:send-test-sms {number : Philippine mobile number, e.g. 09171234567}')]
#[Description('Send one test SMS through UniSMS to check the API key and sender ID')]
class SendTestSms extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(UniSmsChannel $channel): int
    {
        $recipient = PhilippineMobileNumber::normalize((string) $this->argument('number'));

        if ($recipient === null) {
            $this->components->error('Enter a Philippine mobile number, for example 09171234567.');

            return self::FAILURE;
        }

        try {
            $referenceId = $channel->sendText(
                $recipient,
                new UniSmsMessage('This is a test message from '.config('app.name').'. SMS reminders are working.'),
            );
        } catch (RequestException $exception) {
            $this->components->error("UniSMS rejected the message (HTTP {$exception->response->status()}): {$exception->response->body()}");

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($referenceId === null) {
            $this->components->warn('UNISMS_SECRET_KEY is not set, so the message was written to storage/logs/laravel.log instead of being sent.');

            return self::SUCCESS;
        }

        $this->components->info("Sent to {$recipient}. UniSMS reference: {$referenceId}");

        return self::SUCCESS;
    }
}
