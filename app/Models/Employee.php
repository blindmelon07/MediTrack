<?php

namespace App\Models;

use App\Enums\HealthCheckType;
use App\Enums\Role;
use App\Rules\PhilippineMobileNumber;
use Carbon\CarbonImmutable;
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
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $company_id
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
 * @property-read int|null $pending_follow_ups_count
 * @property-read int|null $overdue_follow_ups_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company_id',
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
    use HasFactory, Notifiable;

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
     * Store mobile numbers in the +639XXXXXXXXX form that UniSMS expects.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => blank($value)
                ? null
                : (PhilippineMobileNumber::normalize($value) ?? trim($value)),
        );
    }

    /**
     * Get the mobile number SMS reminders are sent to.
     */
    public function routeNotificationForUnisms(): ?string
    {
        return $this->phone;
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
     * Limit the query to employees the user is allowed to see: everyone for medical staff,
     * their own company for HR, and only themselves for employees.
     *
     * @param  Builder<Employee>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->managesHealthRecords()) {
            return;
        }

        if ($user->hasRole(Role::Hr) && $user->company_id !== null) {
            $query->where('company_id', $user->company_id);

            return;
        }

        $query->where('user_id', $user->id);
    }

    /**
     * Add the date of the employee's most recent full exam as `last_annual_check_on`.
     *
     * @param  Builder<Employee>  $query
     */
    #[Scope]
    protected function withLastAnnualCheck(Builder $query): void
    {
        $query->withMax(
            ['healthChecks as last_annual_check_on' => fn (Builder $query) => $query->whereIn('type', HealthCheckType::annualCheckTypes())],
            'checked_on',
        );
    }

    /**
     * Limit the query to active employees whose annual exam is past due or due within the reminder window.
     *
     * @param  Builder<Employee>  $query
     */
    #[Scope]
    protected function dueForAnnualCheck(Builder $query): void
    {
        $this->withoutAnnualCheckAfter($query->active(), '>', self::annualCheckDueSoonCutoff());
    }

    /**
     * Limit the query to active employees whose annual exam is past due (or who never had one).
     *
     * @param  Builder<Employee>  $query
     */
    #[Scope]
    protected function overdueForAnnualCheck(Builder $query): void
    {
        // An exam exactly one interval ago is due today, which is not yet overdue.
        $this->withoutAnnualCheckAfter($query->active(), '>=', self::annualCheckOverdueCutoff());
    }

    /**
     * Get the date the next annual exam is due, or null when the employee has never had one (due now).
     * Requires the `withLastAnnualCheck` scope.
     */
    public function nextAnnualCheckDueOn(): ?CarbonImmutable
    {
        $lastCheckedOn = $this->getAttribute('last_annual_check_on');

        if ($lastCheckedOn === null) {
            return null;
        }

        return CarbonImmutable::parse($lastCheckedOn)
            ->startOfDay()
            ->addMonthsNoOverflow((int) config('meditrack.annual_check.interval_months'));
    }

    /**
     * Describe where the employee stands on the annual schedule. Requires the `withLastAnnualCheck` scope.
     *
     * @return array{status: 'never'|'overdue'|'due_soon'|'up_to_date', last_checked_on: string|null, due_on: string|null}
     */
    public function annualCheckSummary(): array
    {
        $dueOn = $this->nextAnnualCheckDueOn();
        $reminderStartsOn = $dueOn?->subDays((int) config('meditrack.annual_check.reminder_days'));

        $status = match (true) {
            $dueOn === null => 'never',
            $dueOn->lt(today()) => 'overdue',
            $reminderStartsOn->lte(today()) => 'due_soon',
            default => 'up_to_date',
        };

        $lastCheckedOn = $this->getAttribute('last_annual_check_on');

        return [
            'status' => $status,
            'last_checked_on' => $lastCheckedOn === null ? null : CarbonImmutable::parse($lastCheckedOn)->toDateString(),
            'due_on' => $dueOn?->toDateString(),
        ];
    }

    /**
     * Get the exam date before which an employee is overdue today.
     */
    public static function annualCheckOverdueCutoff(): CarbonImmutable
    {
        return CarbonImmutable::today()->subMonthsNoOverflow((int) config('meditrack.annual_check.interval_months'));
    }

    /**
     * Get the exam date on or before which an employee is due within the reminder window.
     */
    public static function annualCheckDueSoonCutoff(): CarbonImmutable
    {
        return CarbonImmutable::today()
            ->addDays((int) config('meditrack.annual_check.reminder_days'))
            ->subMonthsNoOverflow((int) config('meditrack.annual_check.interval_months'));
    }

    /**
     * Keep employees with no full exam dated after (or on, with '>=') the cutoff.
     *
     * @param  Builder<Employee>  $query
     * @param  '>'|'>='  $operator
     */
    private function withoutAnnualCheckAfter(Builder $query, string $operator, CarbonImmutable $cutoff): void
    {
        $query->whereDoesntHave('healthChecks', fn (Builder $query) => $query
            ->whereIn('type', HealthCheckType::annualCheckTypes())
            ->whereDate('checked_on', $operator, $cutoff));
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
     * Get the company (GSAC Main, GGHI, or Tripple2) the employee works for.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
