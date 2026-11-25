<?php

namespace App\Models;

use App\Enums\ReminderType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Records each reminder that went out, so a reminder is never sent twice on the same day.
 *
 * @property int $id
 * @property ReminderType $reminder_type
 * @property string $subject_type
 * @property int $subject_id
 * @property Carbon $sent_on
 * @property array<int, string> $channels
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['reminder_type', 'subject_type', 'subject_id', 'sent_on', 'channels'])]
class ReminderLog extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reminder_type' => ReminderType::class,
            'sent_on' => 'date:Y-m-d',
            'channels' => 'array',
        ];
    }

    /**
     * Reserve today's reminder for the subject. Returns null when it was already sent today.
     *
     * @param  array<int, string>  $channels
     */
    public static function claim(ReminderType $type, Model $subject, array $channels): ?self
    {
        $log = self::createOrFirst(
            [
                'reminder_type' => $type,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'sent_on' => today()->toDateString(),
            ],
            ['channels' => $channels],
        );

        return $log->wasRecentlyCreated ? $log : null;
    }

    /**
     * Get what the reminder was about: a follow-up, an employee (annual check), or a staff user (digest).
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
