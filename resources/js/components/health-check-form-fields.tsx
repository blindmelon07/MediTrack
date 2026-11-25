import FormField, { FormSection } from '@/components/form-field';
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

export default function HealthCheckFormFields({
    healthCheck,
    employees,
    selectedEmployeeId,
    healthCheckTypes,
    healthCheckResults,
    errors,
    onResultChange,
}: {
    healthCheck?: HealthCheck;
    employees: EmployeeOption[];
    selectedEmployeeId?: number | null;
    healthCheckTypes: Option[];
    healthCheckResults: ResultOption[];
    errors: Record<string, string | undefined>;
    onResultChange?: (result: ResultOption | undefined) => void;
}) {
    return (
        <>
            <FormSection title="Exam">
                <div className="grid gap-4 md:grid-cols-3">
                    <FormField
                        id="employee_id"
                        label="Employee"
                        required
                        error={errors.employee_id}
                    >
                        <NativeSelect
                            id="employee_id"
                            name="employee_id"
                            required
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
                            required
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
                            required
                            max={todayIso()}
                            defaultValue={healthCheck?.checked_on ?? todayIso()}
                        />
                    </FormField>
                    <FormField
                        id="examined_by"
                        label="Examined by"
                        error={errors.examined_by}
                    >
                        <Input
                            id="examined_by"
                            name="examined_by"
                            placeholder="e.g. Dr. Santos"
                            defaultValue={healthCheck?.examined_by ?? ''}
                        />
                    </FormField>
                </div>
            </FormSection>

            <FormSection
                title="Vitals"
                description="Leave blank anything that was not measured."
            >
                <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-5">
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
            </FormSection>

            <FormSection title="Assessment">
                <div className="grid gap-4 md:grid-cols-3">
                    <FormField
                        id="findings"
                        label="Findings"
                        error={errors.findings}
                    >
                        <Textarea
                            id="findings"
                            name="findings"
                            rows={4}
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
                            rows={4}
                            defaultValue={healthCheck?.diagnosis ?? ''}
                        />
                    </FormField>
                    <FormField
                        id="recommendations"
                        label="Recommendations"
                        error={errors.recommendations}
                    >
                        <Textarea
                            id="recommendations"
                            name="recommendations"
                            rows={4}
                            defaultValue={healthCheck?.recommendations ?? ''}
                        />
                    </FormField>
                    <FormField
                        id="result"
                        label="Result"
                        required
                        error={errors.result}
                    >
                        <NativeSelect
                            id="result"
                            name="result"
                            required
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
            </FormSection>
        </>
    );
}
