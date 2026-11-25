<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\HealthCheckResult;
use App\Enums\HealthCheckType;
use Database\Factories\HealthCheckFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property int|null $recorded_by
 * @property HealthCheckType $type
 * @property Carbon $checked_on
 * @property string|null $examined_by
 * @property string|null $blood_pressure
 * @property int|null $heart_rate
 * @property string|null $temperature
 * @property string|null $weight_kg
 * @property string|null $height_cm
 * @property string|null $findings
 * @property string|null $diagnosis
 * @property string|null $recommendations
 * @property HealthCheckResult $result
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'employee_id',
    'type',
    'checked_on',
    'examined_by',
    'blood_pressure',
    'heart_rate',
    'temperature',
    'weight_kg',
    'height_cm',
    'findings',
    'diagnosis',
    'recommendations',
    'result',
])]
class HealthCheck extends Model
{
    /** @use HasFactory<HealthCheckFactory> */
    use Auditable, HasFactory;

    /**
     * Delete stored attachment files along with the health check.
     */
    protected static function booted(): void
    {
        static::deleting(function (HealthCheck $healthCheck): void {
            $healthCheck->attachments->each->delete();
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
            'type' => HealthCheckType::class,
            'result' => HealthCheckResult::class,
            'checked_on' => 'date:Y-m-d',
            'heart_rate' => 'integer',
            'temperature' => 'decimal:1',
            'weight_kg' => 'decimal:1',
            'height_cm' => 'decimal:1',
        ];
    }

    /**
     * Describe the health check for the audit log, without results or diagnosis.
     */
    public function auditLabel(): string
    {
        return strtolower($this->type->label())." health check #{$this->id} dated {$this->checked_on->toDateString()}";
    }

    /**
     * Get the employee who was examined.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user who entered the record.
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Get the follow-ups raised from this health check.
     *
     * @return HasMany<FollowUp, $this>
     */
    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    /**
     * Get the files (such as lab results) attached to this health check.
     *
     * @return HasMany<HealthCheckAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(HealthCheckAttachment::class);
    }

    /**
     * Store an uploaded file on the private disk and attach it to the health check.
     */
    public function storeAttachment(UploadedFile $file): HealthCheckAttachment
    {
        return $this->attachments()->create([
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->store("health-checks/{$this->id}", HealthCheckAttachment::DISK),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }
}
