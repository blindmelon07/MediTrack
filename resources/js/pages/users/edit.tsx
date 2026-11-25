import { Form, Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { PageHeader } from '@/components/form-field';
import UserFormFields from '@/components/user-form-fields';
import type { AccountFormValues } from '@/components/user-form-fields';
import { Button } from '@/components/ui/button';
import { destroy, edit, index, update } from '@/routes/users';
import type { Company, EmployeeOption, Option } from '@/types';

export default function UserEdit({
    account,
    roles,
    companies,
    employees,
}: {
    account: AccountFormValues;
    roles: Option[];
    companies: Company[];
    employees: (EmployeeOption & { email: string | null })[];
}) {
    const { auth } = usePage().props;
    const isSelf = auth.user.id === account.id;

    setLayoutProps({
        breadcrumbs: [
            { title: 'User accounts', href: index() },
            { title: account.name, href: edit(account.id) },
        ],
    });

    return (
        <>
            <Head title={`Edit ${account.name}`} />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={`Edit ${account.name}`}
                    actions={
                        !isSelf && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={destroy(account.id)}
                                    as="button"
                                    onBefore={() =>
                                        confirm(
                                            `Delete ${account.name}'s account? Their employee health record is kept.`,
                                        )
                                    }
                                >
                                    <Trash2 /> Delete account
                                </Link>
                            </Button>
                        )
                    }
                />

                <Form
                    {...update.form(account.id)}
                    resetOnError={['password', 'password_confirmation']}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <UserFormFields
                                account={account}
                                roles={roles}
                                companies={companies}
                                employees={employees}
                                errors={errors}
                            />
                            <div className="flex gap-2">
                                <Button disabled={processing}>
                                    Save changes
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
