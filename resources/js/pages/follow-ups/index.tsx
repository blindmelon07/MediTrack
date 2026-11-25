import { Form, Head, Link } from '@inertiajs/react';
import { AlertTriangle, Plus, Search } from 'lucide-react';
import { PageHeader } from '@/components/form-field';
import { FollowUpStatusBadge } from '@/components/medical-badges';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { formatDate } from '@/lib/medical';
import { cn } from '@/lib/utils';
import { show as showEmployee } from '@/routes/employees';
import { create, edit, index } from '@/routes/follow-ups';
import type { FollowUp, Option, Paginated } from '@/types';

export default function FollowUpIndex({
    followUps,
    filters,
    followUpStatuses,
    overdueCount,
}: {
    followUps: Paginated<FollowUp>;
    filters: { search: string; status: string };
    followUpStatuses: Option[];
    overdueCount: number;
}) {
    return (
        <>
            <Head title="Follow-ups" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Follow-ups"
                    description="Rechecks, referrals, and reviews that still need to happen."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> Schedule follow-up
                            </Link>
                        </Button>
                    }
                />

                {overdueCount > 0 && filters.status !== 'overdue' && (
                    <Link
                        href={index({ query: { status: 'overdue' } })}
                        className="flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800 hover:bg-red-100 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                    >
                        <AlertTriangle className="size-4" />
                        {overdueCount}{' '}
                        {overdueCount === 1 ? 'follow-up is' : 'follow-ups are'}{' '}
                        overdue. View them →
                    </Link>
                )}

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
                        name="status"
                        defaultValue={filters.status}
                        aria-label="Status"
                        className="sm:w-48"
                        onChange={(event) =>
                            event.currentTarget.form?.requestSubmit()
                        }
                    >
                        <option value="overdue">Overdue</option>
                        {followUpStatuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                        <option value="all">All</option>
                    </NativeSelect>
                    <Button type="submit" variant="secondary">
                        <Search /> Search
                    </Button>
                </Form>

                <div className="overflow-x-auto rounded-xl border border-border bg-card">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Due</th>
                                <th className="px-4 py-3 font-medium">
                                    Employee
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Reason
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Assigned to
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {followUps.data.map((followUp) => (
                                <tr
                                    key={followUp.id}
                                    className="border-b last:border-0 hover:bg-muted/30"
                                >
                                    <td
                                        className={cn(
                                            'px-4 py-3 whitespace-nowrap',
                                            followUp.is_overdue &&
                                                'font-medium text-red-700 dark:text-red-400',
                                        )}
                                    >
                                        {formatDate(followUp.due_on)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {followUp.employee && (
                                            <>
                                                <Link
                                                    href={showEmployee(
                                                        followUp.employee.id,
                                                    )}
                                                    className="hover:underline"
                                                >
                                                    {
                                                        followUp.employee
                                                            .full_name
                                                    }
                                                </Link>
                                                <div className="text-xs text-muted-foreground">
                                                    {followUp.employee
                                                        .department ??
                                                        followUp.employee
                                                            .employee_number}
                                                </div>
                                            </>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {followUp.reason}
                                    </td>
                                    <td className="px-4 py-3">
                                        {followUp.assignee?.name ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <FollowUpStatusBadge
                                            followUp={followUp}
                                            options={followUpStatuses}
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link href={edit(followUp.id)}>
                                                Update
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {followUps.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No follow-ups found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={followUps} />
            </div>
        </>
    );
}

FollowUpIndex.layout = {
    breadcrumbs: [{ title: 'Follow-ups', href: index() }],
};
