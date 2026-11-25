import { Form, Head, Link } from '@inertiajs/react';
import EmployeeFormFields from '@/components/employee-form-fields';
import { PageHeader } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { create, index, store } from '@/routes/employees';

export default function EmployeeCreate() {
    return (
        <>
            <Head title="Add employee" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Add employee"
                    description="Create a record so you can log health checks and follow-ups."
                />

                <Form {...store.form()} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <EmployeeFormFields errors={errors} />
                            <div className="flex gap-2">
                                <Button disabled={processing}>
                                    Save employee
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

EmployeeCreate.layout = {
    breadcrumbs: [
        { title: 'Employees', href: index() },
        { title: 'Add employee', href: create() },
    ],
};
