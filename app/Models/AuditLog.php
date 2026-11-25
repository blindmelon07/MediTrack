<?php

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An append-only record of who viewed or changed medical data, for Data Privacy Act accountability.
 * It stores which fields changed, never their values, so the log itself holds no medical details.
 *
 * @property int $id
 * @property int|null $user_id
 * @property AuditAction $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int|null $employee_id
 * @property string $description
 * @property array<int, string>|null $changed_fields
 * @property string|null $ip_address
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'action', 'subject_type', 'subject_id', 'employee_id', 'description', 'changed_fields', 'ip_address'])]
class AuditLog extends Model
{
    /**
     * Audit entries are never edited, so they only have a creation time.
     */
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'changed_fields' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Record an action by the signed-in user (or the system, for scheduled jobs and seeding).
     *
     * @param  array<int, string>  $changedFields
     */
    public static function record(AuditAction $action, ?Model $subject, string $description, ?int $employeeId = null, array $changedFields = []): self
    {
        if ($subject !== null && method_exists($subject, 'auditEmployeeId')) {
            $employeeId ??= $subject->auditEmployeeId();
        }

        return self::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'employee_id' => $employeeId,
            'description' => $description,
            'changed_fields' => $changedFields === [] ? null : array_values($changedFields),
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    /**
     * Get the user who performed the action.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the employee whose record was involved.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
