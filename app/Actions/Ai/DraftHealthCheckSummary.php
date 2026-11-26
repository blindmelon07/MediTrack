<?php

namespace App\Actions\Ai;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\HealthCheck;

/**
 * Drafts a plain-language summary of a health check and suggests follow-ups,
 * for medical staff to review. Only de-identified details are sent.
 */
class DraftHealthCheckSummary
{
    private const string INSTRUCTIONS = <<<'TEXT'
        You help the company clinic of a hospital in the Philippines write up employee health checks.
        You receive one de-identified health check: the employee's age and sex, vitals, and the
        examining staff's notes. Names and contact details have been replaced with [patient] or [removed].

        Write for the clinic's medical staff, who will review and edit your draft before anything is saved.
        - summary: 2 to 4 sentences in plain English stating what was found and what it means for the
          employee's fitness to work. Refer to the person as "the employee". Do not add findings, diagnoses
          or numbers that are not in the record. If the record has little detail, say so briefly.
        - follow_ups: only follow-ups the record actually supports (for example a repeat BP check for
          elevated blood pressure, or a specialist referral named in the recommendations). Each has a
          short reason under 100 characters and due_in_days from today. Return an empty list when nothing
          needs following up.
        TEXT;

    /**
     * The longest a suggested follow-up may be scheduled ahead, in days.
     */
    private const int MAX_DAYS_AHEAD = 365;

    public function __construct(private AiAssistant $assistant, private Deidentifier $deidentifier) {}

    /**
     * Draft the summary and suggested follow-ups for the health check.
     *
     * @return array{summary: string, follow_ups: array<int, array{reason: string, due_on: string}>}
     *
     * @throws AiUnavailableException
     */
    public function handle(HealthCheck $healthCheck): array
    {
        $healthCheck->loadMissing('employee');
        $employee = $healthCheck->employee;

        $record = [
            ...$this->deidentifier->profile($employee),
            'check_type' => $healthCheck->type->label(),
            'result' => $healthCheck->result->label(),
            'blood_pressure_mmHg' => $healthCheck->blood_pressure,
            'heart_rate_bpm' => $healthCheck->heart_rate,
            'temperature_c' => $healthCheck->temperature,
            'weight_kg' => $healthCheck->weight_kg,
            'height_cm' => $healthCheck->height_cm,
            'findings' => $this->deidentifier->scrub($healthCheck->findings, $employee),
            'diagnosis' => $this->deidentifier->scrub($healthCheck->diagnosis, $employee),
            'recommendations' => $this->deidentifier->scrub($healthCheck->recommendations, $employee),
        ];

        $reply = $this->assistant->structured(
            self::INSTRUCTIONS,
            json_encode(array_filter($record, fn (mixed $value): bool => $value !== null && $value !== ''), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            $this->schema(),
        );

        AuditLog::record(AuditAction::AiDrafted, $healthCheck, 'Drafted an AI summary for '.$healthCheck->auditLabel());

        return [
            'summary' => trim((string) ($reply['summary'] ?? '')),
            'follow_ups' => collect($reply['follow_ups'] ?? [])
                ->filter(fn (mixed $followUp): bool => is_array($followUp) && filled($followUp['reason'] ?? null))
                ->map(fn (array $followUp): array => [
                    'reason' => mb_substr(trim((string) $followUp['reason']), 0, 255),
                    'due_on' => today()->addDays(max(1, min(self::MAX_DAYS_AHEAD, (int) ($followUp['due_in_days'] ?? 30))))->toDateString(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Get the JSON schema the reply must follow.
     *
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'summary' => ['type' => 'string'],
                'follow_ups' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'reason' => ['type' => 'string'],
                            'due_in_days' => ['type' => 'integer'],
                        ],
                        'required' => ['reason', 'due_in_days'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['summary', 'follow_ups'],
            'additionalProperties' => false,
        ];
    }
}
