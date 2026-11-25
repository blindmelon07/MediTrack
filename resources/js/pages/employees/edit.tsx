import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { employeeSteps } from '@/components/employee-form-fields';
import { PageHeader } from '@/components/form-field';
import FormSteps from '@/components/form-steps';
import { edit, index, show, update } from '@/routes/employees';
import type { Company, Employee } from '@/types';

export default function EmployeeEdit({
    employee,
    companies,
}: {
    employee: Employee;
    companies: Company[];
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Employees', href: index() },
            { title: employee.full_name, href: show(employee.id) },
            { title: 'Edit', href: edit(employee.id) },
        ],
    });

    return (
        <>
            <Head title={`Edit ${employee.full_name}`} />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
                <PageHeader title={`Edit ${employee.full_name}`} />

                <Form {...update.form(employee.id)} noValidate>
                    {({ processing, errors }) => (
                        <FormSteps
                            steps={employeeSteps({
                                employee,
                                companies,
                                errors,
                            })}
                            errors={errors}
                            processing={processing}
                            submitLabel="Save changes"
                            cancelHref={show(employee.id)}
                        />
                    )}
                </Form>
            </div>
        </>
    );
}
