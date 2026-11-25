import FormField, { FormSection } from '@/components/form-field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import type { EmployeeOption, FollowUp, StaffMember } from '@/types';

export default function FollowUpFormFields({
    followUp,
    employees,
    selectedEmployeeId,
    healthCheckId,
    lockEmployee = false,
    staff,
    errors,
}: {
    followUp?: FollowUp;
    employees: EmployeeOption[];
    selectedEmployeeId?: number | null;
    healthCheckId?: number | null;
    lockEmployee?: boolean;
    staff: StaffMember[];
    errors: Record<string, string | undefined>;
}) {
    const employeeId = followUp?.employee_id ?? selectedEmployeeId ?? '';

    return (
        <FormSection title="Follow-up">
            {healthCheckId && (
                <input
                    type="hidden"
                    name="health_check_id"
                    value={healthCheckId}
                />
            )}
            {lockEmployee && (
                <input type="hidden" name="employee_id" value={employeeId} />
            )}
            <div className="grid gap-4 md:grid-cols-3">
                <FormField
                    id="employee_id"
                    label="Employee"
                    required
                    error={errors.employee_id ?? errors.health_check_id}
                >
                    <NativeSelect
                        id="employee_id"
                        name={lockEmployee ? undefined : 'employee_id'}
                        required
                        disabled={lockEmployee}
                        defaultValue={employeeId}
                    >
                        <option value="" disabled>
                            Select an employee
                        </option>
                        {employees.map((employee) => (
                            <option key={employee.id} value={employee.id}>
                                {employee.full_name} ({employee.employee_number}
                                )
                            </option>
                        ))}
                    </NativeSelect>
                </FormField>
                <FormField
                    id="reason"
                    label="Reason"
                    required
                    error={errors.reason}
                    className="md:col-span-2"
                >
                    <Input
                        id="reason"
                        name="reason"
                        required
                        placeholder="e.g. Repeat fasting blood sugar"
                        defaultValue={followUp?.reason ?? ''}
                    />
                </FormField>
                <FormField
                    id="due_on"
                    label="Due date"
                    required
                    error={errors.due_on}
                >
                    <Input
                        id="due_on"
                        name="due_on"
                        type="date"
                        required
                        defaultValue={followUp?.due_on ?? ''}
                    />
                </FormField>
                <FormField
                    id="assigned_to"
                    label="Assigned to"
                    error={errors.assigned_to}
                >
                    <NativeSelect
                        id="assigned_to"
                        name="assigned_to"
                        defaultValue={followUp?.assigned_to ?? ''}
                    >
                        <option value="">Unassigned</option>
                        {staff.map((member) => (
                            <option key={member.id} value={member.id}>
                                {member.name}
                            </option>
                        ))}
                    </NativeSelect>
                </FormField>
                <FormField
                    id="notes"
                    label="Notes"
                    error={errors.notes}
                    className="md:col-span-3"
                >
                    <Textarea
                        id="notes"
                        name="notes"
                        rows={3}
                        defaultValue={followUp?.notes ?? ''}
                    />
                </FormField>
            </div>
        </FormSection>
    );
}
