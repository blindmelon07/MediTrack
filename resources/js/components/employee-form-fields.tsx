import FormField, { FormSection } from '@/components/form-field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import type { Company, Employee } from '@/types';

export default function EmployeeFormFields({
    employee,
    companies,
    errors,
}: {
    employee?: Employee;
    companies: Company[];
    errors: Record<string, string | undefined>;
}) {
    const companyLocked = companies.length === 1;

    return (
        <>
            <FormSection title="Employee details">
                <div className="grid gap-4 md:grid-cols-3">
                    <FormField
                        id="company_id"
                        label="Company"
                        required
                        error={errors.company_id}
                        hint={
                            companyLocked
                                ? 'HR can only add employees to their own company.'
                                : undefined
                        }
                    >
                        <NativeSelect
                            id="company_id"
                            name="company_id"
                            required
                            disabled={companyLocked}
                            defaultValue={
                                employee?.company_id ??
                                (companyLocked ? companies[0].id : '')
                            }
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
                    <FormField
                        id="employee_number"
                        label="Employee number"
                        required
                        error={errors.employee_number}
                    >
                        <Input
                            id="employee_number"
                            name="employee_number"
                            defaultValue={employee?.employee_number}
                            required
                            autoFocus={!employee}
                        />
                    </FormField>
                    <FormField
                        id="first_name"
                        label="First name"
                        required
                        error={errors.first_name}
                    >
                        <Input
                            id="first_name"
                            name="first_name"
                            defaultValue={employee?.first_name}
                            required
                        />
                    </FormField>
                    <FormField
                        id="last_name"
                        label="Last name"
                        required
                        error={errors.last_name}
                    >
                        <Input
                            id="last_name"
                            name="last_name"
                            defaultValue={employee?.last_name}
                            required
                        />
                    </FormField>
                    <FormField
                        id="birth_date"
                        label="Date of birth"
                        error={errors.birth_date}
                    >
                        <Input
                            id="birth_date"
                            name="birth_date"
                            type="date"
                            defaultValue={employee?.birth_date ?? ''}
                        />
                    </FormField>
                    <FormField id="sex" label="Sex" error={errors.sex}>
                        <NativeSelect
                            id="sex"
                            name="sex"
                            defaultValue={employee?.sex ?? ''}
                        >
                            <option value="">Not specified</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </NativeSelect>
                    </FormField>
                </div>
            </FormSection>

            <FormSection title="Work">
                <div className="grid gap-4 md:grid-cols-3">
                    <FormField
                        id="department"
                        label="Department"
                        error={errors.department}
                    >
                        <Input
                            id="department"
                            name="department"
                            defaultValue={employee?.department ?? ''}
                        />
                    </FormField>
                    <FormField
                        id="position"
                        label="Position"
                        error={errors.position}
                    >
                        <Input
                            id="position"
                            name="position"
                            defaultValue={employee?.position ?? ''}
                        />
                    </FormField>
                    <FormField
                        id="hired_on"
                        label="Date hired"
                        error={errors.hired_on}
                    >
                        <Input
                            id="hired_on"
                            name="hired_on"
                            type="date"
                            defaultValue={employee?.hired_on ?? ''}
                        />
                    </FormField>
                    <FormField
                        id="is_active"
                        label="Employment status"
                        error={errors.is_active}
                        hint="Inactive employees keep their records but are hidden from new check forms."
                    >
                        <NativeSelect
                            id="is_active"
                            name="is_active"
                            defaultValue={
                                employee?.is_active === false ? '0' : '1'
                            }
                        >
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </NativeSelect>
                    </FormField>
                </div>
            </FormSection>

            <FormSection
                title="Contact"
                description="Reminders for follow-ups and annual checks go to these. Messages never include medical details."
            >
                <div className="grid gap-4 md:grid-cols-3">
                    <FormField id="email" label="Email" error={errors.email}>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            defaultValue={employee?.email ?? ''}
                        />
                    </FormField>
                    <FormField
                        id="phone"
                        label="Mobile number"
                        error={errors.phone}
                        hint="Used for SMS reminders, e.g. 0917 123 4567."
                    >
                        <Input
                            id="phone"
                            name="phone"
                            type="tel"
                            inputMode="tel"
                            placeholder="0917 123 4567"
                            defaultValue={employee?.phone ?? ''}
                        />
                    </FormField>
                </div>
            </FormSection>
        </>
    );
}
