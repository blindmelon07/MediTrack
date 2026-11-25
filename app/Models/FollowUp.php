<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\FollowUpStatus;
use Database\Factories\FollowUpFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property int|null $health_check_id
 * @property int|null $assigned_to
 * @property string $reason
 * @property Carbon $due_on
 * @property FollowUpStatus $status
 * @property Carbon|null $completed_on
 * @property string|null $notes
 * @property-read bool $is_overdue
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'employee_id',
    'health_check_id',
    'assigned_to',
    'reason',
    'due_on',
    'status',
    'completed_on',
    'notes',
])]
#[Appends(['is_overdue'])]
class FollowUp extends Model
{
    /** @use HasFactory<FollowUpFactory> */
    use Auditable, HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FollowUpStatus::class,
            'due_on' => 'date:Y-m-d',
            'completed_on' => 'date:Y-m-d',
        ];
    }

    /**
     * Determine whether a pending follow-up is past its due date.
     *
     * @return Attribute<bool, never>
     */
    protected function isOverdue(): Attribute
    {
        return Attribute::get(
            fn (): bool => $this->status === FollowUpStatus::Pending && $this->due_on->lt(today()),
        );
    }

    /**
     * Limit the query to pending follow-ups that are past their due date.
     *
     * @param  Builder<FollowUp>  $query
     */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->where('status', FollowUpStatus::Pending)->whereDate('due_on', '<', today());
    }

    /**
     * Limit the query to pending follow-ups due from today through the given number of days ahead.
     *
     * @param  Builder<FollowUp>  $query
     */
    #[Scope]
    protected function dueWithinDays(Builder $query, int $days): void
    {
        $query->where('status', FollowUpStatus::Pending)
            ->whereDate('due_on', '>=', today())
            ->whereDate('due_on', '<=', today()->addDays($days));
    }

    /**
     * Limit the query to follow-ups of employees the user is allowed to see.
     *
     * @param  Builder<FollowUp>  $query
     */
    #[Scope]
    protected function forEmployeesVisibleTo(Builder $query, User $user): void
    {
        if ($user->managesHealthRecords()) {
            return;
        }

        $query->whereHas('employee', fn (Builder $query) => $query->visibleTo($user));
    }

    /**
     * Describe the follow-up for the audit log, without the medical reason.
     */
    public function auditLabel(): string
    {
        return "follow-up #{$this->id} due {$this->due_on->toDateString()}";
    }

    /**
     * Get the employee the follow-up is for.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the health check that raised the follow-up.
     *
     * @return BelongsTo<HealthCheck, $this>
     */
    public function healthCheck(): BelongsTo
    {
        return $this->belongsTo(HealthCheck::class);
    }

    /**
     * Get the staff member responsible for the follow-up.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
