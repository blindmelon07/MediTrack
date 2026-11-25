import { ClipboardList, HeartPulse, Stethoscope } from 'lucide-react';
import FormField from '@/components/form-field';
import type { FormStep } from '@/components/form-steps';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { todayIso } from '@/lib/medical';
import type {
    EmployeeOption,
    HealthCheck,
    Option,
    ResultOption,
} from '@/types';

type Props = {
    healthCheck?: HealthCheck;
    employees: EmployeeOption[];
    selectedEmployeeId?: number | null;
    healthCheckTypes: Option[];
    healthCheckResults: ResultOption[];
    errors: Record<string, string | undefined>;
    onResultChange?: (result: ResultOption | undefined) => void;
};

/**
 * The Exam, Vitals, and Assessment tabs shared by the record and edit health check forms.
 */
export function healthCheckSteps({
    healthCheck,
    employees,
    selectedEmployeeId,
    healthCheckTypes,
    healthCheckResults,
    errors,
    onResultChange,
}: Props): FormStep[] {
    return [
        {
            id: 'exam',
            label: 'Exam',
            icon: Stethoscope,
            fields: ['employee_id', 'type', 'checked_on', 'examined_by'],
            content: (
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="employee_id"
                        label="Employee"
                        required
                        error={errors.employee_id}
                        className="sm:col-span-2"
                    >
                        <NativeSelect
                            id="employee_id"
                            name="employee_id"
                            defaultValue={
                                healthCheck?.employee_id ??
                                selectedEmployeeId ??
                                ''
                            }
                        >
                            <option value="" disabled>
                                Select an employee
                            </option>
                            {employees.map((employee) => (
                                <option key={employee.id} value={employee.id}>
                                    {employee.full_name} (
                                    {employee.employee_number})
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <FormField
                        id="type"
                        label="Type of check"
                        required
                        error={errors.type}
                    >
                        <NativeSelect
                            id="type"
                            name="type"
                            defaultValue={
                                healthCheck?.type ?? 'annual_physical'
                            }
                        >
                            {healthCheckTypes.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <FormField
                        id="checked_on"
                        label="Date of check"
                        required
                        error={errors.checked_on}
                    >
                        <Input
                            id="checked_on"
                            name="checked_on"
                            type="date"
                            max={todayIso()}
                            defaultValue={healthCheck?.checked_on ?? todayIso()}
                        />
                    </FormField>
                    <FormField
                        id="examined_by"
                        label="Examining physician"
                        error={errors.examined_by}
                        hint="Printed above the signature line."
                        className="sm:col-span-2"
                    >
                        <Input
                            id="examined_by"
                            name="examined_by"
                            placeholder="e.g. Dr. Maria Santos"
                            defaultValue={healthCheck?.examined_by ?? ''}
                        />
                    </FormField>
                </div>
            ),
        },
        {
            id: 'vitals',
            label: 'Vitals',
            icon: HeartPulse,
            fields: [
                'blood_pressure',
                'heart_rate',
                'temperature',
                'weight_kg',
                'height_cm',
            ],
            content: (
                <>
                    <p className="text-sm text-muted-foreground">
                        Leave blank anything that was not measured.
                    </p>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <FormField
                            id="blood_pressure"
                            label="Blood pressure"
                            error={errors.blood_pressure}
                            hint="mmHg, e.g. 120/80"
                        >
                            <Input
                                id="blood_pressure"
                                name="blood_pressure"
                                placeholder="120/80"
                                defaultValue={healthCheck?.blood_pressure ?? ''}
                            />
                        </FormField>
                        <FormField
                            id="heart_rate"
                            label="Heart rate"
                            error={errors.heart_rate}
                            hint="beats per minute"
                        >
                            <Input
                                id="heart_rate"
                                name="heart_rate"
                                type="number"
                                min={20}
                                max={250}
                                defaultValue={healthCheck?.heart_rate ?? ''}
                            />
                        </FormField>
                        <FormField
                            id="temperature"
                            label="Temperature"
                            error={errors.temperature}
                            hint="°C"
                        >
                            <Input
                                id="temperature"
                                name="temperature"
                                type="number"
                                step="0.1"
                                min={30}
                                max={45}
                                defaultValue={healthCheck?.temperature ?? ''}
                            />
                        </FormField>
                        <FormField
                            id="weight_kg"
                            label="Weight"
                            error={errors.weight_kg}
                            hint="kg"
                        >
                            <Input
                                id="weight_kg"
                                name="weight_kg"
                                type="number"
                                step="0.1"
                                min={1}
                                max={500}
                                defaultValue={healthCheck?.weight_kg ?? ''}
                            />
                        </FormField>
                        <FormField
                            id="height_cm"
                            label="Height"
                            error={errors.height_cm}
                            hint="cm"
                        >
                            <Input
                                id="height_cm"
                                name="height_cm"
                                type="number"
                                step="0.1"
                                min={30}
                                max={260}
                                defaultValue={healthCheck?.height_cm ?? ''}
                            />
                        </FormField>
                    </div>
                </>
            ),
        },
        {
            id: 'assessment',
            label: 'Assessment',
            icon: ClipboardList,
            fields: ['findings', 'diagnosis', 'recommendations', 'result'],
            content: (
                <div className="grid gap-4">
                    <FormField
                        id="findings"
                        label="Findings"
                        error={errors.findings}
                    >
                        <Textarea
                            id="findings"
                            name="findings"
                            rows={3}
                            defaultValue={healthCheck?.findings ?? ''}
                        />
                    </FormField>
                    <FormField
                        id="diagnosis"
                        label="Diagnosis"
                        error={errors.diagnosis}
                    >
                        <Textarea
                            id="diagnosis"
                            name="diagnosis"
                            rows={3}
                            defaultValue={healthCheck?.diagnosis ?? ''}
                        />
                    </FormField>
                    <FormField
                        id="recommendations"
                        label="Recommendations / restrictions"
                        error={errors.recommendations}
                        hint="Shown as remarks on the medical certificate."
                    >
                        <Textarea
                            id="recommendations"
                            name="recommendations"
                            rows={3}
                            defaultValue={healthCheck?.recommendations ?? ''}
                        />
                    </FormField>
                    <FormField
                        id="result"
                        label="Result"
                        required
                        error={errors.result}
                        className="sm:max-w-xs"
                    >
                        <NativeSelect
                            id="result"
                            name="result"
                            defaultValue={healthCheck?.result ?? 'normal'}
                            onChange={(event) =>
                                onResultChange?.(
                                    healthCheckResults.find(
                                        (result) =>
                                            result.value === event.target.value,
                                    ),
                                )
                            }
                        >
                            {healthCheckResults.map((result) => (
                                <option key={result.value} value={result.value}>
                                    {result.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                </div>
            ),
        },
    ];
}
