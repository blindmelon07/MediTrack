<?php

namespace Tests\Fakes;

use App\Actions\Ai\AiAssistant;
use App\Actions\Ai\AiUnavailableException;

/**
 * Stands in for Claude in tests: returns a queued reply and records what would have been sent.
 */
class FakeAiAssistant implements AiAssistant
{
    /**
     * @var array<int, array{instructions: string, input: string, schema: array<string, mixed>, attachments: array<int, array<string, mixed>>}>
     */
    public array $requests = [];

    /**
     * @param  array<string, mixed>|AiUnavailableException  $reply
     */
    public function __construct(private array|AiUnavailableException $reply = [], private bool $available = true) {}

    /**
     * Replace the assistant in the container with a fake that gives the reply.
     *
     * @param  array<string, mixed>|AiUnavailableException  $reply
     */
    public static function swap(array|AiUnavailableException $reply = []): self
    {
        $fake = new self($reply);
        app()->instance(AiAssistant::class, $fake);

        return $fake;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<int, array<string, mixed>>  $attachments
     * @return array<string, mixed>
     */
    public function structured(string $instructions, string $input, array $schema, array $attachments = []): array
    {
        $this->requests[] = compact('instructions', 'input', 'schema', 'attachments');

        if ($this->reply instanceof AiUnavailableException) {
            throw $this->reply;
        }

        return $this->reply;
    }

    /**
     * Get everything sent to the model as one string, for checking what was left out.
     */
    public function sentText(): string
    {
        return collect($this->requests)
            ->map(fn (array $request): string => $request['instructions']."\n".$request['input'])
            ->implode("\n");
    }
}
