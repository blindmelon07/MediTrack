import { Form, Head, Link } from '@inertiajs/react';
import FollowUpFormFields from '@/components/follow-up-form-fields';
import { PageHeader } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/medical';
import { create, index, store } from '@/routes/follow-ups';
import type { EmployeeOption, HealthCheck, StaffMember } from '@/types';

export default function FollowUpCreate({
    employees,
    selectedEmployeeId,
    healthCheck,
    staff,
}: {
    employees: EmployeeOption[];
    selectedEmployeeId: number | null;
    healthCheck: Pick<
        HealthCheck,
        'id' | 'employee_id' | 'checked_on' | 'recommendations'
    > | null;
    staff: StaffMember[];
}) {
    return (
        <>
            <Head title="Schedule follow-up" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Schedule follow-up"
                    description={
                        healthCheck
                            ? `From the health check on ${formatDate(healthCheck.checked_on)}.${healthCheck.recommendations ? ` Recommendation: ${healthCheck.recommendations}` : ''}`
                            : undefined
                    }
                />

                <Form {...store.form()} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <FollowUpFormFields
                                employees={employees}
                                selectedEmployeeId={selectedEmployeeId}
                                healthCheckId={healthCheck?.id}
                                lockEmployee={healthCheck !== null}
                                staff={staff}
                                errors={errors}
                            />
                            <div className="flex gap-2">
                                <Button disabled={processing}>
                                    Schedule follow-up
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={index()}>Cancel</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

FollowUpCreate.layout = {
    breadcrumbs: [
        { title: 'Follow-ups', href: index() },
        { title: 'Schedule', href: create() },
    ],
};
