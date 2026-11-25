import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import FollowUpFormFields from '@/components/follow-up-form-fields';
import FormField, { FormSection, PageHeader } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { formatDate, todayIso } from '@/lib/medical';
import { show as showEmployee } from '@/routes/employees';
import { destroy, edit, index, update } from '@/routes/follow-ups';
import type { EmployeeOption, FollowUp, Option, StaffMember } from '@/types';

export default function FollowUpEdit({
    followUp,
    employees,
    staff,
    followUpStatuses,
}: {
    followUp: FollowUp;
    employees: EmployeeOption[];
    staff: StaffMember[];
    followUpStatuses: Option[];
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Follow-ups', href: index() },
            { title: 'Update', href: edit(followUp.id) },
        ],
    });

    const [status, setStatus] = useState(followUp.status);

    return (
        <>
            <Head title="Update follow-up" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Update follow-up"
                    description={
                        followUp.employee && (
                            <Link
                                href={showEmployee(followUp.employee.id)}
                                className="hover:underline"
                            >
                                {followUp.employee.full_name}
                                {followUp.health_check &&
                                    ` · from health check on ${formatDate(followUp.health_check.checked_on)}`}
                            </Link>
                        )
                    }
                    actions={
                        <Button variant="outline" asChild>
                            <Link
                                href={destroy(followUp.id)}
                                as="button"
                                onBefore={() =>
                                    confirm('Delete this follow-up?')
                                }
                            >
                                Delete
                            </Link>
                        </Button>
                    }
                />

                <Form {...update.form(followUp.id)} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <FormSection
                                title="Outcome"
                                description="Mark the follow-up done, missed, or cancelled once it is resolved."
                            >
                                <div className="grid gap-4 md:grid-cols-3">
                                    <FormField
                                        id="status"
                                        label="Status"
                                        required
                                        error={errors.status}
                                    >
                                        <NativeSelect
                                            id="status"
                                            name="status"
                                            value={status}
                                            onChange={(event) =>
                                                setStatus(event.target.value)
                                            }
                                        >
                                            {followUpStatuses.map((option) => (
                                                <option
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </FormField>
                                    {status === 'completed' && (
                                        <FormField
                                            id="completed_on"
                                            label="Completed on"
                                            error={errors.completed_on}
                                        >
                                            <Input
                                                id="completed_on"
                                                name="completed_on"
                                                type="date"
                                                max={todayIso()}
                                                defaultValue={
                                                    followUp.completed_on ??
                                                    todayIso()
                                                }
                                            />
                                        </FormField>
                                    )}
                                </div>
                            </FormSection>

                            <FollowUpFormFields
                                followUp={followUp}
                                employees={employees}
                                healthCheckId={followUp.health_check_id}
                                lockEmployee={followUp.health_check_id !== null}
                                staff={staff}
                                errors={errors}
                            />

                            <div className="flex gap-2">
                                <Button disabled={processing}>Save</Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={showEmployee(
                                            followUp.employee_id,
                                        )}
                                    >
                                        Cancel
                                    </Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
