import { Form, Head, Link } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { PageHeader } from '@/components/form-field';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { optionLabel } from '@/lib/medical';
import { show as showEmployee } from '@/routes/employees';
import { create, edit, index } from '@/routes/users';
import type { Company, EmployeeOption, Option, Paginated, User } from '@/types';

type UserRow = User & {
    company: Company | null;
    employee: EmployeeOption | null;
};

export default function UserIndex({
    users,
    filters,
    roles,
}: {
    users: Paginated<UserRow>;
    filters: { search: string };
    roles: Option[];
}) {
    return (
        <>
            <Head title="User accounts" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="User accounts"
                    description="Who can log in and what they can see. New accounts can only be created here."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> Create account
                            </Link>
                        </Button>
                    }
                />

                <Form
                    {...index.form()}
                    className="flex flex-col gap-2 sm:flex-row"
                >
                    <Input
                        name="search"
                        defaultValue={filters.search}
                        placeholder="Search name or email"
                        aria-label="Search accounts"
                        className="sm:max-w-sm"
                    />
                    <Button type="submit" variant="secondary">
                        <Search /> Search
                    </Button>
                </Form>

                <div className="overflow-x-auto rounded-xl border border-border bg-card">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Role</th>
                                <th className="px-4 py-3 font-medium">
                                    Company / record
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((user) => (
                                <tr
                                    key={user.id}
                                    className="border-b last:border-0 hover:bg-muted/30"
                                >
                                    <td className="px-4 py-3">
                                        <div className="font-medium">
                                            {user.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {user.email}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant="secondary">
                                            {optionLabel(roles, user.role)}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        {user.role === 'hr' &&
                                            (user.company?.name ?? (
                                                <span className="text-red-700 dark:text-red-400">
                                                    No company set
                                                </span>
                                            ))}
                                        {user.role === 'employee' &&
                                            (user.employee ? (
                                                <Link
                                                    href={showEmployee(
                                                        user.employee.id,
                                                    )}
                                                    className="hover:underline"
                                                >
                                                    {user.employee.full_name}
                                                </Link>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    Not linked
                                                </span>
                                            ))}
                                        {(user.role === 'admin' ||
                                            user.role === 'medical_staff') && (
                                            <span className="text-muted-foreground">
                                                All companies
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link href={edit(user.id)}>
                                                Edit
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {users.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No accounts found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={users} />
            </div>
        </>
    );
}

UserIndex.layout = {
    breadcrumbs: [{ title: 'User accounts', href: index() }],
};
