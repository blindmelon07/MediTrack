<?php

namespace App\Actions\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * Talks to Claude through the Anthropic API.
 */
class ClaudeAssistant implements AiAssistant
{
    /**
     * Server-side fallback lets a substitute model answer if Claude declines for policy reasons.
     */
    private const string FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    /**
     * Determine whether AI assistance is switched on and has credentials.
     */
    public function isAvailable(): bool
    {
        return (bool) config('meditrack.ai.enabled') && filled(config('services.anthropic.key'));
    }

    /**
     * Ask Claude for a reply shaped by the given JSON schema.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<int, array<string, mixed>>  $attachments
     * @return array<string, mixed>
     */
    public function structured(string $instructions, string $input, array $schema, array $attachments = []): array
    {
        if (! $this->isAvailable()) {
            throw AiUnavailableException::disabled();
        }

        $client = new Client(apiKey: (string) config('services.anthropic.key'));

        try {
            $message = $client->beta->messages->create(
                model: (string) config('meditrack.ai.model'),
                maxTokens: 16000,
                system: $instructions,
                messages: [[
                    'role' => 'user',
                    'content' => [...$attachments, ['type' => 'text', 'text' => $input]],
                ]],
                outputConfig: [
                    'effort' => (string) config('meditrack.ai.effort'),
                    'format' => ['type' => 'json_schema', 'schema' => $schema],
                ],
                fallbacks: 'default',
                betas: [self::FALLBACK_BETA],
                requestOptions: ['timeout' => 120.0],
            );
        } catch (APIException $exception) {
            Log::warning('AI request failed', ['error' => $exception->getMessage()]);

            throw AiUnavailableException::unreachable();
        }

        if ($message->stopReason !== 'end_turn') {
            Log::warning('AI request ended without an answer', ['stop_reason' => $message->stopReason]);

            throw AiUnavailableException::noAnswer();
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                try {
                    $reply = json_decode($block->text, true, flags: JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    throw AiUnavailableException::noAnswer();
                }

                return is_array($reply) ? $reply : throw AiUnavailableException::noAnswer();
            }
        }

        throw AiUnavailableException::noAnswer();
    }
}
