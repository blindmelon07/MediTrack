import { Form, Head, setLayoutProps } from '@inertiajs/react';
import DocumentPreviewPane from '@/components/document-preview-pane';
import { PageHeader } from '@/components/form-field';
import FormSteps from '@/components/form-steps';
import { healthCheckSteps } from '@/components/health-check-form-fields';
import { documentEmployee } from '@/components/health-check-document';
import { useLiveFormValues } from '@/hooks/use-live-form-values';
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

    const { values, bind } = useLiveFormValues();
    const employee = employees.find(
        (option) => String(option.id) === values.employee_id,
    );

    return (
        <>
            <Head title="Edit health check" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Edit health check"
                    description="To add files or follow-ups, use the buttons on the health check page."
                />

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)]">
                    <div {...bind}>
                        <Form {...update.form(healthCheck.id)} noValidate>
                            {({ processing, errors }) => (
                                <FormSteps
                                    errors={errors}
                                    processing={processing}
                                    submitLabel="Save changes"
                                    cancelHref={show(healthCheck.id)}
                                    steps={healthCheckSteps({
                                        healthCheck,
                                        employees,
                                        healthCheckTypes,
                                        healthCheckResults,
                                        errors,
                                    })}
                                />
                            )}
                        </Form>
                    </div>

                    <DocumentPreviewPane
                        values={{ ...values, id: healthCheck.id }}
                        employee={documentEmployee(employee)}
                        healthCheckTypes={healthCheckTypes}
                        healthCheckResults={healthCheckResults}
                    />
                </div>
            </div>
        </>
    );
}
