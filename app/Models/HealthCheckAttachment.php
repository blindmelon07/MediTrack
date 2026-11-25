<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $health_check_id
 * @property string $original_name
 * @property string $path
 * @property string|null $mime_type
 * @property int $size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['original_name', 'path', 'mime_type', 'size'])]
#[Hidden(['path'])]
class HealthCheckAttachment extends Model
{
    use Auditable;

    /**
     * The private disk that stores medical attachments.
     */
    public const DISK = 'local';

    /**
     * Remove the stored file when the record is deleted.
     */
    protected static function booted(): void
    {
        static::deleted(function (HealthCheckAttachment $attachment): void {
            Storage::disk(self::DISK)->delete($attachment->path);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * Describe the file for the audit log.
     */
    public function auditLabel(): string
    {
        return "file \"{$this->original_name}\" on health check #{$this->health_check_id}";
    }

    /**
     * Attachments belong to the employee of their health check.
     */
    public function auditEmployeeId(): ?int
    {
        return $this->healthCheck?->employee_id;
    }

    /**
     * Get the health check the file belongs to.
     *
     * @return BelongsTo<HealthCheck, $this>
     */
    public function healthCheck(): BelongsTo
    {
        return $this->belongsTo(HealthCheck::class);
    }
}
