import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import EmployeeFormFields from '@/components/employee-form-fields';
import { PageHeader } from '@/components/form-field';
import { Button } from '@/components/ui/button';
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
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
                <PageHeader title={`Edit ${employee.full_name}`} />

                <Form {...update.form(employee.id)} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <EmployeeFormFields
                                employee={employee}
                                companies={companies}
                                errors={errors}
                            />
                            <div className="flex gap-2">
                                <Button disabled={processing}>
                                    Save changes
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={show(employee.id)}>Cancel</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
