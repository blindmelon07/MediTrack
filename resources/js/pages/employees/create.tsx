import { Form, Head } from '@inertiajs/react';
import { employeeSteps } from '@/components/employee-form-fields';
import { PageHeader } from '@/components/form-field';
import FormSteps from '@/components/form-steps';
import { create, index, store } from '@/routes/employees';
import type { Company } from '@/types';

export default function EmployeeCreate({
    companies,
}: {
    companies: Company[];
}) {
    return (
        <>
            <Head title="Add employee" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Add employee"
                    description="Create a record so you can log health checks, follow-ups, and appointments."
                />

                <Form {...store.form()} noValidate>
                    {({ processing, errors }) => (
                        <FormSteps
                            steps={employeeSteps({ companies, errors })}
                            errors={errors}
                            processing={processing}
                            submitLabel="Save employee"
                            cancelHref={index()}
                        />
                    )}
                </Form>
            </div>
        </>
    );
}

EmployeeCreate.layout = {
    breadcrumbs: [
        { title: 'Employees', href: index() },
        { title: 'Add employee', href: create() },
    ],
};
