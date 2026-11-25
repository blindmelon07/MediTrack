import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { CalendarPlus, ClipboardPlus, Paperclip, Pencil } from 'lucide-react';
import { PageHeader } from '@/components/form-field';
import { FollowUpStatusBadge, ResultBadge } from '@/components/medical-badges';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate, optionLabel } from '@/lib/medical';
import { edit, index, show } from '@/routes/employees';
import {
    create as createFollowUp,
    edit as editFollowUp,
} from '@/routes/follow-ups';
import {
    create as createHealthCheck,
    show as showHealthCheck,
} from '@/routes/health-checks';
import type {
    Employee,
    FollowUp,
    HealthCheck,
    Option,
    ResultOption,
} from '@/types';

function Detail({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm">{value || '—'}</dd>
        </div>
    );
}

export default function EmployeeShow({
    employee,
    healthChecks,
    followUps,
    healthCheckTypes,
    healthCheckResults,
    followUpStatuses,
}: {
    employee: Employee;
    healthChecks: HealthCheck[];
    followUps: FollowUp[];
    healthCheckTypes: Option[];
    healthCheckResults: ResultOption[];
    followUpStatuses: Option[];
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Employees', href: index() },
            { title: employee.full_name, href: show(employee.id) },
        ],
    });

    const openFollowUps = followUps.filter(
        (followUp) => followUp.status === 'pending',
    );
    const closedFollowUps = followUps.filter(
        (followUp) => followUp.status !== 'pending',
    );

    return (
        <>
            <Head title={employee.full_name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={employee.full_name}
                    description={
                        <span className="flex items-center gap-2">
                            {employee.employee_number}
                            {!employee.is_active && (
                                <Badge variant="outline">Inactive</Badge>
                            )}
                        </span>
                    }
                    actions={
                        <>
                            <Button asChild>
                                <Link
                                    href={createHealthCheck({
                                        query: { employee_id: employee.id },
                                    })}
                                >
                                    <ClipboardPlus /> Record health check
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link
                                    href={createFollowUp({
                                        query: { employee_id: employee.id },
                                    })}
                                >
                                    <CalendarPlus /> Schedule follow-up
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={edit(employee.id)}>
                                    <Pencil /> Edit
                                </Link>
                            </Button>
                        </>
                    }
                />

                <dl className="grid gap-4 rounded-xl border border-sidebar-border/70 p-4 sm:grid-cols-2 md:grid-cols-4 dark:border-sidebar-border">
                    <Detail label="Department" value={employee.department} />
                    <Detail label="Position" value={employee.position} />
                    <Detail
                        label="Date hired"
                        value={formatDate(employee.hired_on)}
                    />
                    <Detail
                        label="Date of birth"
                        value={formatDate(employee.birth_date)}
                    />
                    <Detail
                        label="Sex"
                        value={
                            employee.sex
                                ? employee.sex[0].toUpperCase() +
                                  employee.sex.slice(1)
                                : null
                        }
                    />
                    <Detail label="Email" value={employee.email} />
                    <Detail label="Phone" value={employee.phone} />
                </dl>

                <section className="space-y-3">
                    <h2 className="text-base font-medium">
                        Open follow-ups ({openFollowUps.length})
                    </h2>
                    <FollowUpList
                        followUps={openFollowUps}
                        statuses={followUpStatuses}
                        emptyText="No open follow-ups."
                    />
                </section>

                <section className="space-y-3">
                    <h2 className="text-base font-medium">
                        Health check history ({healthChecks.length})
                    </h2>
                    <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Date
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Type
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Result
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Diagnosis
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Examined by
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {healthChecks.map((healthCheck) => (
                                    <tr
                                        key={healthCheck.id}
                                        className="border-b last:border-0 hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            <Link
                                                href={showHealthCheck(
                                                    healthCheck.id,
                                                )}
                                                className="font-medium hover:underline"
                                            >
                                                {formatDate(
                                                    healthCheck.checked_on,
                                                )}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3">
                                            {optionLabel(
                                                healthCheckTypes,
                                                healthCheck.type,
                                            )}
                                            {(healthCheck.attachments_count ??
                                                0) > 0 && (
                                                <span className="ml-2 inline-flex items-center gap-1 text-xs text-muted-foreground">
                                                    <Paperclip className="size-3" />
                                                    {
                                                        healthCheck.attachments_count
                                                    }
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <ResultBadge
                                                result={healthCheck.result}
                                                options={healthCheckResults}
                                            />
                                        </td>
                                        <td className="max-w-xs truncate px-4 py-3">
                                            {healthCheck.diagnosis ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {healthCheck.examined_by ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                                {healthChecks.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-4 py-10 text-center text-muted-foreground"
                                        >
                                            No health checks recorded yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                {closedFollowUps.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="text-base font-medium">
                            Past follow-ups ({closedFollowUps.length})
                        </h2>
                        <FollowUpList
                            followUps={closedFollowUps}
                            statuses={followUpStatuses}
                            emptyText=""
                        />
                    </section>
                )}
            </div>
        </>
    );
}

function FollowUpList({
    followUps,
    statuses,
    emptyText,
}: {
    followUps: FollowUp[];
    statuses: Option[];
    emptyText: string;
}) {
    if (followUps.length === 0) {
        return <p className="text-sm text-muted-foreground">{emptyText}</p>;
    }

    return (
        <ul className="divide-y rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            {followUps.map((followUp) => (
                <li
                    key={followUp.id}
                    className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="font-medium">
                                {followUp.reason}
                            </span>
                            <FollowUpStatusBadge
                                followUp={followUp}
                                options={statuses}
                            />
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Due {formatDate(followUp.due_on)}
                            {followUp.completed_on &&
                                ` · Completed ${formatDate(followUp.completed_on)}`}
                            {followUp.assignee &&
                                ` · Assigned to ${followUp.assignee.name}`}
                            {followUp.health_check_id && (
                                <>
                                    {' · '}
                                    <Link
                                        href={showHealthCheck(
                                            followUp.health_check_id,
                                        )}
                                        className="underline"
                                    >
                                        From health check
                                    </Link>
                                </>
                            )}
                        </p>
                        {followUp.notes && (
                            <p className="text-sm text-muted-foreground">
                                {followUp.notes}
                            </p>
                        )}
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={editFollowUp(followUp.id)}>
                            {followUp.status === 'pending' ? 'Update' : 'Edit'}
                        </Link>
                    </Button>
                </li>
            ))}
        </ul>
    );
}
