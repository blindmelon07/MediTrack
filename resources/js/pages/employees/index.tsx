import { Form, Head, Link } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { PageHeader } from '@/components/form-field';
import { ResultBadge } from '@/components/medical-badges';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { formatDate, optionLabel } from '@/lib/medical';
import { create, index, show } from '@/routes/employees';
import type {
    Employee,
    HealthCheck,
    Option,
    Paginated,
    ResultOption,
} from '@/types';

type EmployeeRow = Employee & {
    latest_health_check: HealthCheck | null;
    pending_follow_ups_count: number;
};

export default function EmployeeIndex({
    employees,
    filters,
    healthCheckTypes,
    healthCheckResults,
}: {
    employees: Paginated<EmployeeRow>;
    filters: { search: string; status: string };
    healthCheckTypes: Option[];
    healthCheckResults: ResultOption[];
}) {
    return (
        <>
            <Head title="Employees" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Employees"
                    description="Everyone whose health checks and follow-ups the office tracks."
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
                    <NativeSelect
                        name="status"
                        defaultValue={filters.status}
                        aria-label="Employment status"
                        className="sm:w-40"
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
                                <th className="px-4 py-3 font-medium">
                                    Department
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Last health check
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Pending follow-ups
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
                                    <td className="px-4 py-3">
                                        {employee.department ?? '—'}
                                        {employee.position && (
                                            <div className="text-xs text-muted-foreground">
                                                {employee.position}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {employee.latest_health_check ? (
                                            <div className="flex flex-col items-start gap-1">
                                                <span>
                                                    {formatDate(
                                                        employee
                                                            .latest_health_check
                                                            .checked_on,
                                                    )}{' '}
                                                    ·{' '}
                                                    {optionLabel(
                                                        healthCheckTypes,
                                                        employee
                                                            .latest_health_check
                                                            .type,
                                                    )}
                                                </span>
                                                <ResultBadge
                                                    result={
                                                        employee
                                                            .latest_health_check
                                                            .result
                                                    }
                                                    options={healthCheckResults}
                                                />
                                            </div>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                None recorded
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {employee.pending_follow_ups_count >
                                        0 ? (
                                            <Badge variant="secondary">
                                                {
                                                    employee.pending_follow_ups_count
                                                }
                                            </Badge>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                0
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {employees.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
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
