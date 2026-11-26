<?php

namespace App\Actions\Ai;

use RuntimeException;

/**
 * Thrown when the AI assistant is switched off, unreachable, or declines a request.
 * The message is safe to show to staff.
 */
class AiUnavailableException extends RuntimeException
{
    /**
     * Create the exception for when AI is switched off or has no API key.
     */
    public static function disabled(): self
    {
        return new self(__('AI assistance is not set up yet.'));
    }

    /**
     * Create the exception for when the AI service could not be reached.
     */
    public static function unreachable(): self
    {
        return new self(__('The AI assistant is not responding right now. Please try again in a moment.'));
    }

    /**
     * Create the exception for when the model declined or returned an unusable reply.
     */
    public static function noAnswer(): self
    {
        return new self(__('The AI assistant could not draft an answer for this record.'));
    }
}
