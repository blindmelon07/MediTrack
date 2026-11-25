<?php

namespace App\Concerns;

use App\Enums\AuditAction;
use App\Models\AuditLog;

/**
 * Writes an audit log entry whenever the model is created, updated, or deleted.
 * Models describe themselves with auditLabel() and may override auditEmployeeId().
 */
trait Auditable
{
    /**
     * Fields whose changes are not worth an audit entry on their own.
     *
     * @var array<int, string>
     */
    protected static array $auditIgnoredFields = ['updated_at', 'created_at', 'remember_token'];

    /**
     * Register the model event listeners.
     */
    public static function bootAuditable(): void
    {
        static::created(function (self $model): void {
            AuditLog::record(AuditAction::Created, $model, 'Created '.$model->auditLabel());
        });

        static::updated(function (self $model): void {
            $changedFields = array_values(array_diff(array_keys($model->getChanges()), static::$auditIgnoredFields));

            if ($changedFields !== []) {
                AuditLog::record(AuditAction::Updated, $model, 'Updated '.$model->auditLabel(), changedFields: $changedFields);
            }
        });

        static::deleted(function (self $model): void {
            AuditLog::record(AuditAction::Deleted, $model, 'Deleted '.$model->auditLabel());
        });
    }

    /**
     * Describe the record for the audit log without including medical details.
     */
    abstract public function auditLabel(): string;

    /**
     * Get the employee whose record this belongs to, so their access history can be listed.
     */
    public function auditEmployeeId(): ?int
    {
        $employeeId = $this->getAttribute('employee_id');

        return $employeeId === null ? null : (int) $employeeId;
    }
}
