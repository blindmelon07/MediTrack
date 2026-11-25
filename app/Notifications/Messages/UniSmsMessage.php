<?php

namespace App\Notifications\Messages;

use Illuminate\Support\Str;

/**
 * A text message for the UniSMS channel.
 */
class UniSmsMessage
{
    /**
     * UniSMS rejects messages longer than this.
     */
    public const MAX_LENGTH = 670;

    public function __construct(public string $content) {}

    /**
     * Get the content in the form UniSMS accepts: plain ASCII (it rejects emoji and most
     * accented letters) and no longer than the gateway limit.
     */
    public function sanitizedContent(): string
    {
        return Str::limit(Str::squish(Str::ascii($this->content)), self::MAX_LENGTH, '');
    }
}
