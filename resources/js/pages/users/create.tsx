import { Form, Head, Link } from '@inertiajs/react';
import { PageHeader } from '@/components/form-field';
import UserFormFields from '@/components/user-form-fields';
import { Button } from '@/components/ui/button';
import { create, index, store } from '@/routes/users';
import type { Company, EmployeeOption, Option } from '@/types';

export default function UserCreate({
    roles,
    companies,
    employees,
}: {
    roles: Option[];
    companies: Company[];
    employees: (EmployeeOption & { email: string | null })[];
}) {
    return (
        <>
            <Head title="Create account" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
                <PageHeader title="Create account" />

                <Form
                    {...store.form()}
                    resetOnError={['password', 'password_confirmation']}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <UserFormFields
                                roles={roles}
                                companies={companies}
                                employees={employees}
                                errors={errors}
                            />
                            <div className="flex gap-2">
                                <Button disabled={processing}>
                                    Create account
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

UserCreate.layout = {
    breadcrumbs: [
        { title: 'User accounts', href: index() },
        { title: 'Create', href: create() },
    ],
};
