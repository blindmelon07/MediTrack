<?php

namespace Database\Seeders;

use App\Enums\FollowUpStatus;
use App\Enums\HealthCheckResult;
use App\Enums\HealthCheckType;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FollowUp;
use App\Models\HealthCheck;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Demo data for trying Meditrack: staff accounts for every role and ten employees per
 * company, each in a different situation (up to date, due soon, overdue, abnormal result
 * with a follow-up, awaiting lab results, and so on). Dates are relative to today.
 *
 * Run with: php artisan db:seed --class=DemoSeeder
 *
 * Every account's password is "password". Emails use the reserved .test domain and
 * employees have no mobile numbers, so no real person can be messaged by accident.
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD = 'password';

    /**
     * Ten names per company, in company order.
     *
     * @var array<int, array{string, string, string}> first name, last name, sex
     */
    private const NAMES = [
        ['Juan', 'Dela Cruz', 'male'], ['Maria', 'Santos', 'female'], ['Jose', 'Reyes', 'male'],
        ['Ana', 'Garcia', 'female'], ['Mark', 'Bautista', 'male'], ['Kristine', 'Mendoza', 'female'],
        ['Paolo', 'Villanueva', 'male'], ['Angelica', 'Ramos', 'female'], ['Carlo', 'Aquino', 'male'],
        ['Liza', 'Castillo', 'female'], ['Ramon', 'Flores', 'male'], ['Joy', 'Navarro', 'female'],
        ['Miguel', 'Torres', 'male'], ['Patricia', 'Gonzales', 'female'], ['Rafael', 'Lopez', 'male'],
        ['Camille', 'Rivera', 'female'], ['Andres', 'Domingo', 'male'], ['Bea', 'Salazar', 'female'],
        ['Noel', 'Mercado', 'male'], ['Grace', 'Pascual', 'female'], ['Dennis', 'Aguilar', 'male'],
        ['Rachelle', 'Fernandez', 'female'], ['Arnel', 'Valdez', 'male'], ['Shiela', 'Cortez', 'female'],
        ['Jerome', 'Manalo', 'male'], ['Katrina', 'Soriano', 'female'], ['Rodel', 'Ocampo', 'male'],
        ['Mae', 'Tolentino', 'female'], ['Vincent', 'Javier', 'male'], ['Lorna', 'Sison', 'female'],
    ];

    /**
     * @var array<string, string> company name => employee number prefix
     */
    private const COMPANY_PREFIXES = ['GSAC Main' => 'GSAC', 'GGHI' => 'GGHI', 'Tripple2' => 'T2'];

    private const DEPARTMENTS = ['Operations', 'Finance', 'Human Resources', 'IT', 'Sales', 'Warehouse', 'Admin'];

    private CarbonImmutable $today;

    private User $doctor;

    private User $nurse;

    private int $nameIndex = 0;

    /**
     * Seed the demo data.
     */
    public function run(): void
    {
        if (User::query()->where('email', 'admin@meditrack.test')->exists()) {
            $this->command->warn('Demo data is already loaded (admin@meditrack.test exists). Nothing was changed.');

            return;
        }

        $this->today = CarbonImmutable::today();

        $employeeLoginName = DB::transaction(function (): string {
            $this->createStaff();

            foreach (self::COMPANY_PREFIXES as $companyName => $prefix) {
                $company = Company::query()->firstOrCreate(['name' => $companyName]);

                foreach (range(0, 9) as $scenario) {
                    $this->createEmployee($company, $prefix, $scenario);
                }
            }

            return $this->linkEmployeeAccount();
        });

        $this->command->table(['Role', 'Log in with', 'Password'], [
            ['Administrator', 'admin@meditrack.test', self::PASSWORD],
            ['Medical staff (doctor)', 'doctor@meditrack.test', self::PASSWORD],
            ['Medical staff (nurse)', 'nurse@meditrack.test', self::PASSWORD],
            ['HR - GSAC Main', 'hr.gsac@meditrack.test', self::PASSWORD],
            ['HR - GGHI', 'hr.gghi@meditrack.test', self::PASSWORD],
            ['HR - Tripple2', 'hr.tripple2@meditrack.test', self::PASSWORD],
            ["Employee ({$employeeLoginName}, GGHI)", 'employee@meditrack.test', self::PASSWORD],
        ]);
    }

    /**
     * Create one account for each staff role.
     */
    private function createStaff(): void
    {
        $this->account('Clinic Administrator', 'admin@meditrack.test', Role::Admin);
        $this->doctor = $this->account('Dr. Teresa Lim', 'doctor@meditrack.test', Role::MedicalStaff);
        $this->nurse = $this->account('Nurse Ben Abad', 'nurse@meditrack.test', Role::MedicalStaff);

        foreach (['GSAC Main' => 'hr.gsac', 'GGHI' => 'hr.gghi', 'Tripple2' => 'hr.tripple2'] as $companyName => $login) {
            $company = Company::query()->firstOrCreate(['name' => $companyName]);
            $this->account("{$companyName} HR", "{$login}@meditrack.test", Role::Hr, $company);
        }
    }

    /**
     * Create a verified account.
     */
    private function account(string $name, string $email, Role $role, ?Company $company = null): User
    {
        $user = new User(['name' => $name, 'email' => $email, 'password' => self::PASSWORD]);
        $user->forceFill(['role' => $role, 'company_id' => $company?->id, 'email_verified_at' => now()])->save();

        return $user;
    }

    /**
     * Create an employee and give them the health history for one of ten situations.
     *
     * @param  int<0, 9>  $scenario
     */
    private function createEmployee(Company $company, string $prefix, int $scenario): void
    {
        [$firstName, $lastName, $sex] = self::NAMES[$this->nameIndex++];

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'employee_number' => sprintf('%s-%04d', $prefix, $scenario + 1),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'sex' => $sex,
            'department' => self::DEPARTMENTS[($scenario + $this->nameIndex) % count(self::DEPARTMENTS)],
            'position' => $scenario % 3 === 0 ? 'Supervisor' : 'Staff',
            'email' => Str::slug("{$firstName}.{$lastName}", '.').'@'.Str::slug($company->name).'.test',
            'phone' => null,
            'birth_date' => $this->today->subYears(24 + $this->nameIndex)->subDays($this->nameIndex * 11)->toDateString(),
            'hired_on' => $scenario === 3 ? $this->today->subMonths(2)->toDateString() : $this->today->subYears(1 + $scenario % 6)->toDateString(),
            'is_active' => $scenario !== 9,
        ]);

        match ($scenario) {
            // Up to date: normal annual physical four months ago.
            0 => $this->check($employee, HealthCheckType::AnnualPhysical, $this->today->subMonths(4), HealthCheckResult::Normal),

            // Annual check due within the month.
            1 => $this->check($employee, HealthCheckType::AnnualPhysical, $this->today->subMonths(11)->subDays(10), HealthCheckResult::Normal),

            // Annual check overdue by two months.
            2 => $this->check($employee, HealthCheckType::AnnualPhysical, $this->today->subMonths(14), HealthCheckResult::Normal),

            // New hire, never examined.
            3 => null,

            // High blood pressure found; the recheck is now overdue.
            4 => $this->followUp(
                $this->check($employee, HealthCheckType::AnnualPhysical, $this->today->subMonths(2), HealthCheckResult::Abnormal, [
                    'blood_pressure' => '152/96',
                    'findings' => 'Elevated blood pressure on two readings.',
                    'diagnosis' => 'Stage 2 hypertension',
                    'recommendations' => 'Start low-salt diet; recheck BP in 6 weeks.',
                ]),
                'Recheck blood pressure',
                $this->today->subDays(6),
                $this->nurse,
            ),

            // High blood sugar on a lab test; review in three days (an SMS reminder goes out today).
            5 => $this->scenarioHighBloodSugar($employee),

            // Back strain: cleared with restrictions; follow-up due today, not yet assigned.
            6 => $this->scenarioBackStrain($employee),

            // New hire cleared at pre-employment; lab results still pending.
            7 => $this->scenarioNewHireAwaitingLabs($employee),

            // High cholesterol: first follow-up done, a second one next week.
            8 => $this->scenarioCholesterol($employee),

            // Resigned employee with an old record.
            9 => $this->check($employee, HealthCheckType::AnnualPhysical, $this->today->subMonths(20), HealthCheckResult::Normal),
        };
    }

    private function scenarioHighBloodSugar(Employee $employee): void
    {
        $this->check($employee, HealthCheckType::AnnualPhysical, $this->today->subMonths(6), HealthCheckResult::Normal);

        $this->followUp(
            $this->check($employee, HealthCheckType::LabTest, $this->today->subWeeks(3), HealthCheckResult::Abnormal, [
                'findings' => 'Fasting blood sugar 128 mg/dL.',
                'diagnosis' => 'Impaired fasting glucose',
                'recommendations' => 'Repeat FBS and HbA1c; dietary counseling.',
            ]),
            'Repeat fasting blood sugar and HbA1c',
            $this->today->addDays(3),
            $this->doctor,
        );
    }

    private function scenarioBackStrain(Employee $employee): void
    {
        $this->check($employee, HealthCheckType::AnnualPhysical, $this->today->subMonths(8), HealthCheckResult::Normal);

        $this->followUp(
            $this->check($employee, HealthCheckType::FitnessToWork, $this->today->subWeeks(2), HealthCheckResult::FitWithRestrictions, [
                'findings' => 'Lower back pain after lifting; limited flexion.',
                'diagnosis' => 'Lumbar strain',
                'recommendations' => 'No lifting over 10 kg for 2 weeks; physical therapy.',
            ]),
            'Re-evaluate lifting restriction',
            $this->today,
            null,
        );
    }

    private function scenarioNewHireAwaitingLabs(Employee $employee): void
    {
        $this->check($employee, HealthCheckType::PreEmployment, $this->today->subWeeks(3), HealthCheckResult::Fit, [
            'findings' => 'No significant findings.',
        ]);
        $this->check($employee, HealthCheckType::LabTest, $this->today->subDays(2), HealthCheckResult::Pending, [
            'findings' => 'CBC and urinalysis sent to the laboratory.',
            'blood_pressure' => null,
        ]);
    }

    private function scenarioCholesterol(Employee $employee): void
    {
        $check = $this->check($employee, HealthCheckType::AnnualPhysical, $this->today->subMonths(10), HealthCheckResult::Abnormal, [
            'findings' => 'Total cholesterol 245 mg/dL, LDL 160 mg/dL.',
            'diagnosis' => 'Dyslipidemia',
            'recommendations' => 'Diet and exercise; repeat lipid profile in 3 months.',
        ]);

        $this->followUp($check, 'Repeat lipid profile', $this->today->subMonths(7), $this->doctor, FollowUpStatus::Completed, 'Improved: LDL 128 mg/dL.');
        $this->followUp($check, 'Lipid profile and medication review', $this->today->addDays(6), $this->doctor);
    }

    /**
     * Record a health check with typical vitals.
     *
     * @param  array<string, mixed>  $details
     */
    private function check(Employee $employee, HealthCheckType $type, CarbonImmutable $checkedOn, HealthCheckResult $result, array $details = []): HealthCheck
    {
        $healthCheck = new HealthCheck([
            'employee_id' => $employee->id,
            'type' => $type,
            'checked_on' => $checkedOn->toDateString(),
            'examined_by' => $this->doctor->name,
            'blood_pressure' => (110 + $employee->id % 20).'/'.(70 + $employee->id % 12),
            'heart_rate' => 64 + $employee->id % 20,
            'temperature' => 36.5,
            'weight_kg' => 55 + $employee->id % 30,
            'height_cm' => 155 + $employee->id % 25,
            'findings' => 'Unremarkable physical examination.',
            'result' => $result,
            ...$details,
        ]);
        $healthCheck->recorded_by = $this->doctor->id;
        $healthCheck->save();

        return $healthCheck;
    }

    /**
     * Schedule a follow-up from a health check.
     */
    private function followUp(
        HealthCheck $healthCheck,
        string $reason,
        CarbonImmutable $dueOn,
        ?User $assignee,
        FollowUpStatus $status = FollowUpStatus::Pending,
        ?string $notes = null,
    ): FollowUp {
        return FollowUp::query()->create([
            'employee_id' => $healthCheck->employee_id,
            'health_check_id' => $healthCheck->id,
            'assigned_to' => $assignee?->id,
            'reason' => $reason,
            'due_on' => $dueOn->toDateString(),
            'status' => $status,
            'completed_on' => $status === FollowUpStatus::Completed ? $dueOn->toDateString() : null,
            'notes' => $notes,
        ]);
    }

    /**
     * Give the demo employee login a record with something to see: GGHI-0005 has high blood
     * pressure and an overdue recheck. Returns that employee's name.
     */
    private function linkEmployeeAccount(): string
    {
        $employee = Employee::query()->where('employee_number', 'GGHI-0005')->firstOrFail();
        $user = $this->account("{$employee->first_name} {$employee->last_name}", 'employee@meditrack.test', Role::Employee);

        $employee->forceFill(['user_id' => $user->id])->save();

        return $user->name;
    }
}
