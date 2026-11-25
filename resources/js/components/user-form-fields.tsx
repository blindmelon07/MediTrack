import { useState } from 'react';
import FormField, { FormSection } from '@/components/form-field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import type { Company, EmployeeOption, Option, Role } from '@/types';

export type AccountFormValues = {
    id: number;
    name: string;
    email: string;
    role: Role;
    company_id: number | null;
    employee_id: number | null;
};

const roleDescriptions: Record<Role, string> = {
    admin: 'Full access, including creating and editing user accounts.',
    medical_staff:
        'Doctors and nurses. Records health checks, follow-ups, and lab files for all companies.',
    hr: 'Sees clearance and follow-up due dates for one company. Cannot see diagnoses or lab results.',
    employee: 'Can only view their own health record.',
};

export default function UserFormFields({
    account,
    roles,
    companies,
    employees,
    errors,
}: {
    account?: AccountFormValues;
    roles: Option[];
    companies: Company[];
    employees: (EmployeeOption & { email: string | null })[];
    errors: Record<string, string | undefined>;
}) {
    const [role, setRole] = useState<Role>(account?.role ?? 'employee');

    return (
        <>
            <FormSection title="Account">
                <div className="grid gap-4 md:grid-cols-2">
                    <FormField
                        id="name"
                        label="Full name"
                        required
                        error={errors.name}
                    >
                        <Input
                            id="name"
                            name="name"
                            required
                            autoComplete="off"
                            defaultValue={account?.name}
                        />
                    </FormField>
                    <FormField
                        id="email"
                        label="Email (used to log in)"
                        required
                        error={errors.email}
                    >
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            required
                            autoComplete="off"
                            defaultValue={account?.email}
                        />
                    </FormField>
                </div>
            </FormSection>

            <FormSection title="Access">
                <div className="grid gap-4 md:grid-cols-2">
                    <FormField
                        id="role"
                        label="Role"
                        required
                        error={errors.role}
                        hint={roleDescriptions[role]}
                    >
                        <NativeSelect
                            id="role"
                            name="role"
                            value={role}
                            onChange={(event) =>
                                setRole(event.target.value as Role)
                            }
                        >
                            {roles.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>

                    {role === 'hr' && (
                        <FormField
                            id="company_id"
                            label="Company"
                            required
                            error={errors.company_id}
                            hint="HR only sees employees of this company."
                        >
                            <NativeSelect
                                id="company_id"
                                name="company_id"
                                required
                                defaultValue={account?.company_id ?? ''}
                            >
                                <option value="" disabled>
                                    Select a company
                                </option>
                                {companies.map((company) => (
                                    <option key={company.id} value={company.id}>
                                        {company.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                    )}

                    {role === 'employee' && (
                        <FormField
                            id="employee_id"
                            label="Employee record"
                            error={errors.employee_id}
                            hint="Links this login to the employee's health record."
                        >
                            <NativeSelect
                                id="employee_id"
                                name="employee_id"
                                defaultValue={account?.employee_id ?? ''}
                            >
                                <option value="">Not linked yet</option>
                                {employees.map((employee) => (
                                    <option
                                        key={employee.id}
                                        value={employee.id}
                                    >
                                        {employee.full_name} (
                                        {employee.employee_number})
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                    )}
                </div>
            </FormSection>

            <FormSection
                title="Password"
                description={
                    account
                        ? 'Leave blank to keep the current password.'
                        : 'Share it with the person securely. They can change it under Settings.'
                }
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <FormField
                        id="password"
                        label="Password"
                        required={!account}
                        error={errors.password}
                    >
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            required={!account}
                            autoComplete="new-password"
                        />
                    </FormField>
                    <FormField
                        id="password_confirmation"
                        label="Confirm password"
                        required={!account}
                    >
                        <Input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            required={!account}
                            autoComplete="new-password"
                        />
                    </FormField>
                </div>
            </FormSection>
        </>
    );
}
