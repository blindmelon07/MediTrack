<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $employee_number
 * @property string $first_name
 * @property string $last_name
 * @property-read string $full_name
 * @property string|null $department
 * @property string|null $position
 * @property string|null $email
 * @property string|null $phone
 * @property Carbon|null $birth_date
 * @property string|null $sex
 * @property Carbon|null $hired_on
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'employee_number',
    'first_name',
    'last_name',
    'department',
    'position',
    'email',
    'phone',
    'birth_date',
    'sex',
    'hired_on',
    'is_active',
])]
#[Appends(['full_name'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'hired_on' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the employee's name as "Last, First" for listings and selects.
     *
     * @return Attribute<non-falsy-string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => "{$this->last_name}, {$this->first_name}");
    }

    /**
     * Filter employees by name, employee number, or department.
     *
     * @param  Builder<Employee>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $query) use ($term): void {
            $query->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('employee_number', 'like', "%{$term}%")
                ->orWhere('department', 'like', "%{$term}%");
        });
    }

    /**
     * Limit the query to employees who still work at the office.
     *
     * @param  Builder<Employee>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Get the employees that can be picked in a form, keeping an already selected (possibly inactive) employee.
     *
     * @return Collection<int, Employee>
     */
    public static function selectOptions(?int $selectedId = null): Collection
    {
        return self::query()
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhere('id', $selectedId))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'employee_number', 'first_name', 'last_name']);
    }

    /**
     * Get the login account linked to the employee, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the employee's health checks.
     *
     * @return HasMany<HealthCheck, $this>
     */
    public function healthChecks(): HasMany
    {
        return $this->hasMany(HealthCheck::class);
    }

    /**
     * Get the employee's most recent health check.
     *
     * @return HasOne<HealthCheck, $this>
     */
    public function latestHealthCheck(): HasOne
    {
        return $this->hasOne(HealthCheck::class)->latestOfMany('checked_on');
    }

    /**
     * Get the employee's follow-ups.
     *
     * @return HasMany<FollowUp, $this>
     */
    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }
}
