import { Form, Head, Link } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { PageHeader } from '@/components/form-field';
import { ResultBadge } from '@/components/medical-badges';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { formatDate, optionLabel } from '@/lib/medical';
import { show as showEmployee } from '@/routes/employees';
import { create, index, show } from '@/routes/health-checks';
import type { HealthCheck, Option, Paginated, ResultOption } from '@/types';

export default function HealthCheckIndex({
    healthChecks,
    filters,
    healthCheckTypes,
    healthCheckResults,
}: {
    healthChecks: Paginated<HealthCheck>;
    filters: { search: string; type: string; result: string };
    healthCheckTypes: Option[];
    healthCheckResults: ResultOption[];
}) {
    return (
        <>
            <Head title="Health checks" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Health checks"
                    description="Every exam, lab test, and clearance recorded for employees."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> Record health check
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
                        placeholder="Search employee"
                        aria-label="Search employee"
                        className="sm:max-w-xs"
                    />
                    <NativeSelect
                        name="type"
                        defaultValue={filters.type}
                        aria-label="Type"
                        className="sm:w-48"
                        onChange={(event) =>
                            event.currentTarget.form?.requestSubmit()
                        }
                    >
                        <option value="">All types</option>
                        {healthCheckTypes.map((type) => (
                            <option key={type.value} value={type.value}>
                                {type.label}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        name="result"
                        defaultValue={filters.result}
                        aria-label="Result"
                        className="sm:w-48"
                        onChange={(event) =>
                            event.currentTarget.form?.requestSubmit()
                        }
                    >
                        <option value="">All results</option>
                        {healthCheckResults.map((result) => (
                            <option key={result.value} value={result.value}>
                                {result.label}
                            </option>
                        ))}
                    </NativeSelect>
                    <Button type="submit" variant="secondary">
                        <Search /> Search
                    </Button>
                </Form>

                <div className="overflow-x-auto rounded-xl border border-border bg-card">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Date</th>
                                <th className="px-4 py-3 font-medium">
                                    Employee
                                </th>
                                <th className="px-4 py-3 font-medium">Type</th>
                                <th className="px-4 py-3 font-medium">
                                    Result
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Examined by
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {healthChecks.data.map((healthCheck) => (
                                <tr
                                    key={healthCheck.id}
                                    className="border-b last:border-0 hover:bg-muted/30"
                                >
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <Link
                                            href={show(healthCheck.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {formatDate(healthCheck.checked_on)}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">
                                        {healthCheck.employee && (
                                            <>
                                                <Link
                                                    href={showEmployee(
                                                        healthCheck.employee.id,
                                                    )}
                                                    className="hover:underline"
                                                >
                                                    {
                                                        healthCheck.employee
                                                            .full_name
                                                    }
                                                </Link>
                                                <div className="text-xs text-muted-foreground">
                                                    {healthCheck.employee
                                                        .department ??
                                                        healthCheck.employee
                                                            .employee_number}
                                                </div>
                                            </>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {optionLabel(
                                            healthCheckTypes,
                                            healthCheck.type,
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <ResultBadge
                                            result={healthCheck.result}
                                            options={healthCheckResults}
                                        />
                                    </td>
                                    <td className="px-4 py-3">
                                        {healthCheck.examined_by ?? '—'}
                                    </td>
                                </tr>
                            ))}
                            {healthChecks.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No health checks found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={healthChecks} />
            </div>
        </>
    );
}

HealthCheckIndex.layout = {
    breadcrumbs: [{ title: 'Health checks', href: index() }],
};
