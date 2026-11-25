import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { PageHeader } from '@/components/form-field';
import HealthCheckFormFields from '@/components/health-check-form-fields';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/medical';
import { edit, index, show, update } from '@/routes/health-checks';
import type {
    EmployeeOption,
    HealthCheck,
    Option,
    ResultOption,
} from '@/types';

export default function HealthCheckEdit({
    healthCheck,
    employees,
    healthCheckTypes,
    healthCheckResults,
}: {
    healthCheck: HealthCheck;
    employees: EmployeeOption[];
    healthCheckTypes: Option[];
    healthCheckResults: ResultOption[];
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Health checks', href: index() },
            {
                title: formatDate(healthCheck.checked_on),
                href: show(healthCheck.id),
            },
            { title: 'Edit', href: edit(healthCheck.id) },
        ],
    });

    return (
        <>
            <Head title="Edit health check" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Edit health check"
                    description="To add files or follow-ups, use the buttons on the health check page."
                />

                <Form {...update.form(healthCheck.id)} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <HealthCheckFormFields
                                healthCheck={healthCheck}
                                employees={employees}
                                healthCheckTypes={healthCheckTypes}
                                healthCheckResults={healthCheckResults}
                                errors={errors}
                            />
                            <div className="flex gap-2">
                                <Button disabled={processing}>
                                    Save changes
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={show(healthCheck.id)}>
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
