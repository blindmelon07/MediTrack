import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { PageHeader } from '@/components/form-field';
import { ClearanceBadge, ResultBadge } from '@/components/medical-badges';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { formatDate, optionLabel } from '@/lib/medical';
import { create, index, show } from '@/routes/employees';
import type {
    Company,
    EmployeeListRow,
    Option,
    Paginated,
    ResultOption,
} from '@/types';

export default function EmployeeIndex({
    employees,
    filters,
    companies,
    healthCheckTypes,
    healthCheckResults,
}: {
    employees: Paginated<EmployeeListRow>;
    filters: { search: string; status: string; company_id: number | null };
    companies: Company[];
    healthCheckTypes: Option[];
    healthCheckResults: ResultOption[];
}) {
    const { auth } = usePage().props;
    const showCompany = companies.length > 0;

    return (
        <>
            <Head title="Employees" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Employees"
                    description={
                        auth.can.manageHealthRecords
                            ? 'Everyone whose health checks and follow-ups the office tracks.'
                            : 'Health clearance and follow-up status for your company. Medical details are visible to medical staff only.'
                    }
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> Add employee
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
                        placeholder="Search name, employee no., or department"
                        aria-label="Search employees"
                        className="sm:max-w-sm"
                    />
                    {showCompany && (
                        <NativeSelect
                            name="company_id"
                            defaultValue={filters.company_id ?? ''}
                            aria-label="Company"
                            className="sm:w-44"
                            onChange={(event) =>
                                event.currentTarget.form?.requestSubmit()
                            }
                        >
                            <option value="">All companies</option>
                            {companies.map((company) => (
                                <option key={company.id} value={company.id}>
                                    {company.name}
                                </option>
                            ))}
                        </NativeSelect>
                    )}
                    <NativeSelect
                        name="status"
                        defaultValue={filters.status}
                        aria-label="Employment status"
                        className="sm:w-36"
                        onChange={(event) =>
                            event.currentTarget.form?.requestSubmit()
                        }
                    >
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="all">All</option>
                    </NativeSelect>
                    <Button type="submit" variant="secondary">
                        <Search /> Search
                    </Button>
                </Form>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Employee
                                </th>
                                {showCompany && (
                                    <th className="px-4 py-3 font-medium">
                                        Company
                                    </th>
                                )}
                                <th className="px-4 py-3 font-medium">
                                    Department
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Last health check
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Follow-ups
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {employees.data.map((employee) => (
                                <tr
                                    key={employee.id}
                                    className="border-b last:border-0 hover:bg-muted/30"
                                >
                                    <td className="px-4 py-3">
                                        <Link
                                            href={show(employee.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {employee.full_name}
                                        </Link>
                                        <div className="text-xs text-muted-foreground">
                                            {employee.employee_number}
                                            {!employee.is_active && (
                                                <Badge
                                                    variant="outline"
                                                    className="ml-2"
                                                >
                                                    Inactive
                                                </Badge>
                                            )}
                                        </div>
                                    </td>
                                    {showCompany && (
                                        <td className="px-4 py-3">
                                            {employee.company ?? '—'}
                                        </td>
                                    )}
                                    <td className="px-4 py-3">
                                        {employee.department ?? '—'}
                                        {employee.position && (
                                            <div className="text-xs text-muted-foreground">
                                                {employee.position}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {employee.latest_check ? (
                                            <div className="flex flex-col items-start gap-1">
                                                <span>
                                                    {formatDate(
                                                        employee.latest_check
                                                            .checked_on,
                                                    )}{' '}
                                                    ·{' '}
                                                    {optionLabel(
                                                        healthCheckTypes,
                                                        employee.latest_check
                                                            .type,
                                                    )}
                                                </span>
                                                {employee.latest_check
                                                    .result ? (
                                                    <ResultBadge
                                                        result={
                                                            employee
                                                                .latest_check
                                                                .result
                                                        }
                                                        options={
                                                            healthCheckResults
                                                        }
                                                    />
                                                ) : (
                                                    <ClearanceBadge
                                                        clearance={
                                                            employee
                                                                .latest_check
                                                                .clearance
                                                        }
                                                    />
                                                )}
                                            </div>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                None recorded
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {employee.overdue_follow_ups_count >
                                                0 && (
                                                <Badge className="border-transparent bg-red-600 text-white dark:bg-red-700">
                                                    {
                                                        employee.overdue_follow_ups_count
                                                    }{' '}
                                                    overdue
                                                </Badge>
                                            )}
                                            {employee.pending_follow_ups_count >
                                            0 ? (
                                                <Badge variant="secondary">
                                                    {
                                                        employee.pending_follow_ups_count
                                                    }{' '}
                                                    pending
                                                </Badge>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    None
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {employees.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={showCompany ? 5 : 4}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No employees found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={employees} />
            </div>
        </>
    );
}

EmployeeIndex.layout = {
    breadcrumbs: [{ title: 'Employees', href: index() }],
};
