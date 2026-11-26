<?php

namespace App\Actions\Ai;

/**
 * Sends a prompt to the language model and returns its reply as data that
 * matches a JSON schema. Bound to ClaudeAssistant; tests swap in a fake.
 */
interface AiAssistant
{
    /**
     * Determine whether AI assistance is switched on and has credentials.
     */
    public function isAvailable(): bool;

    /**
     * Ask the model for a reply shaped by the given JSON schema.
     *
     * @param  array<string, mixed>  $schema  JSON schema for the reply object.
     * @param  array<int, array<string, mixed>>  $attachments  Content blocks (documents or images) sent before the text.
     * @return array<string, mixed>
     *
     * @throws AiUnavailableException When the model cannot be reached or declines the request.
     */
    public function structured(string $instructions, string $input, array $schema, array $attachments = []): array;
}
