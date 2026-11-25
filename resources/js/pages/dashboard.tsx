import { Head, Link } from '@inertiajs/react';
import {
    CalendarClock,
    CalendarDays,
    CalendarX2,
    ClipboardList,
    FlaskConical,
    HeartPulse,
    Lock,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/form-field';
import { AppointmentStatusBadge } from '@/components/appointment-badges';
import { AnnualCheckBadge, ResultBadge } from '@/components/medical-badges';
import StatTile from '@/components/stat-tile';
import { Button } from '@/components/ui/button';
import { formatDate, formatDateTime, optionLabel } from '@/lib/medical';
import { dashboard } from '@/routes';
import {
    create as createAppointment,
    index as appointmentsIndex,
} from '@/routes/appointments';
import {
    index as employeesIndex,
    show as showEmployee,
} from '@/routes/employees';
import {
    edit as editFollowUp,
    index as followUpsIndex,
} from '@/routes/follow-ups';
import {
    create as createHealthCheck,
    index as healthChecksIndex,
    show as showHealthCheck,
} from '@/routes/health-checks';
import type { AnnualCheckSummary, Option, ResultOption } from '@/types';

type Settings = {
    annualIntervalMonths: number;
    annualReminderDays: number;
    followUpLookaheadDays: number;
};

type ScheduleStats = {
    overdueFollowUps: number;
    upcomingFollowUps: number;
    annualChecksOverdue: number;
    annualChecksDueSoon: number;
    activeEmployees: number;
};

type EmployeeRef = {
    id: number;
    full_name: string;
    employee_number: string;
    company: string | null;
};

type AttentionFollowUp = {
    id: number;
    due_on: string;
    is_overdue: boolean;
    employee: EmployeeRef;
    reason?: string;
    assignee?: string | null;
};

type AnnualDueRow = EmployeeRef & { annual_check: AnnualCheckSummary };

type CompanyRow = {
    id: number;
    name: string;
    active_employees_count: number;
    annual_overdue_count: number;
    overdue_follow_ups_count: number;
};

type AwaitingResult = {
    id: number;
    type: string;
    checked_on: string;
    employee: { id: number; full_name: string; employee_number: string };
};

type OwnRecord = {
    employee: { id: number; full_name: string; employee_number: string };
    annualCheck: AnnualCheckSummary;
    latestCheck: {
        id: number;
        type: string;
        checked_on: string;
        result: string;
    } | null;
    openFollowUps: { id: number; reason: string; due_on: string }[];
    nextAppointment: string | null;
};

type TodayAppointment = {
    id: number;
    time: string;
    purpose: string;
    status: string;
    employee: { id: number; full_name: string; employee_number: string };
};

type Shared = {
    healthCheckTypes: Option[];
    healthCheckResults: ResultOption[];
    appointmentPurposes: Option[];
    appointmentStatuses: Option[];
    settings: Settings;
};

type Props = Shared &
    (
        | {
              view: 'medical';
              stats: ScheduleStats & { awaitingResults: number };
              overdueFollowUps: AttentionFollowUp[];
              upcomingFollowUps: AttentionFollowUp[];
              annualChecksDue: AnnualDueRow[];
              awaitingResults: AwaitingResult[];
              todayAppointments: TodayAppointment[];
              companies: CompanyRow[];
          }
        | {
              view: 'hr';
              companyName: string | null;
              stats: ScheduleStats;
              overdueFollowUps: AttentionFollowUp[];
              upcomingFollowUps: AttentionFollowUp[];
              annualChecksDue: AnnualDueRow[];
          }
        | { view: 'employee'; ownRecord: OwnRecord | null }
    );

export default function Dashboard(props: Props) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                {props.view === 'medical' && <MedicalDashboard {...props} />}
                {props.view === 'hr' && <HrDashboard {...props} />}
                {props.view === 'employee' && (
                    <EmployeeDashboard
                        ownRecord={props.ownRecord}
                        settings={props.settings}
                        healthCheckTypes={props.healthCheckTypes}
                        healthCheckResults={props.healthCheckResults}
                    />
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};

function MedicalDashboard(props: Extract<Props, { view: 'medical' }>) {
    const { stats, settings } = props;

    return (
        <>
            <PageHeader
                title="What needs attention"
                description={`Across all companies · ${stats.activeEmployees.toLocaleString()} active employees`}
                actions={
                    <Button asChild>
                        <Link href={createHealthCheck()}>
                            Record health check
                        </Link>
                    </Button>
                }
            />

            <section
                aria-label="Summary"
                className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5"
            >
                <StatTile
                    label="Overdue follow-ups"
                    value={stats.overdueFollowUps}
                    icon={CalendarX2}
                    tone="critical"
                    href={followUpsIndex({ query: { status: 'overdue' } })}
                />
                <StatTile
                    label={`Follow-ups in next ${settings.followUpLookaheadDays} days`}
                    value={stats.upcomingFollowUps}
                    icon={CalendarClock}
                    tone="warning"
                    href={followUpsIndex()}
                />
                <StatTile
                    label="Annual checks overdue"
                    value={stats.annualChecksOverdue}
                    hint="Includes employees never examined"
                    icon={HeartPulse}
                    tone="critical"
                    href={employeesIndex({ query: { annual: 'overdue' } })}
                />
                <StatTile
                    label={`Annual checks due in ${settings.annualReminderDays} days`}
                    value={stats.annualChecksDueSoon}
                    icon={ClipboardList}
                    tone="warning"
                    href={employeesIndex({ query: { annual: 'due' } })}
                />
                <StatTile
                    label="Awaiting lab results"
                    value={stats.awaitingResults}
                    icon={FlaskConical}
                    tone="warning"
                    href={healthChecksIndex({ query: { result: 'pending' } })}
                />
            </section>

            <div className="grid gap-6 xl:grid-cols-2">
                <Panel
                    title="Overdue follow-ups"
                    viewAllHref={followUpsIndex({
                        query: { status: 'overdue' },
                    })}
                    isTruncated={
                        props.overdueFollowUps.length < stats.overdueFollowUps
                    }
                >
                    <FollowUpRows
                        followUps={props.overdueFollowUps}
                        emptyText="No overdue follow-ups."
                        showDetails
                    />
                </Panel>

                <Panel
                    title={`Follow-ups due in the next ${settings.followUpLookaheadDays} days`}
                    viewAllHref={followUpsIndex()}
                    isTruncated={
                        props.upcomingFollowUps.length < stats.upcomingFollowUps
                    }
                >
                    <FollowUpRows
                        followUps={props.upcomingFollowUps}
                        emptyText="Nothing due this week."
                        showDetails
                    />
                </Panel>

                <Panel
                    title="Annual checks due"
                    description={`Every ${settings.annualIntervalMonths} months, counted from the last annual or pre-employment exam.`}
                    viewAllHref={employeesIndex({ query: { annual: 'due' } })}
                    isTruncated={
                        props.annualChecksDue.length <
                        stats.annualChecksOverdue + stats.annualChecksDueSoon
                    }
                >
                    <AnnualDueRows rows={props.annualChecksDue} canRecord />
                </Panel>

                <Panel
                    title="Awaiting lab results"
                    viewAllHref={healthChecksIndex({
                        query: { result: 'pending' },
                    })}
                    isTruncated={
                        props.awaitingResults.length < stats.awaitingResults
                    }
                >
                    {props.awaitingResults.length === 0 ? (
                        <EmptyRow>No results pending.</EmptyRow>
                    ) : (
                        props.awaitingResults.map((healthCheck) => (
                            <Row key={healthCheck.id}>
                                <div className="min-w-0">
                                    <Link
                                        href={showHealthCheck(healthCheck.id)}
                                        className="font-medium hover:underline"
                                    >
                                        {healthCheck.employee.full_name}
                                    </Link>
                                    <p className="text-xs text-muted-foreground">
                                        {optionLabel(
                                            props.healthCheckTypes,
                                            healthCheck.type,
                                        )}{' '}
                                        · {formatDate(healthCheck.checked_on)}
                                    </p>
                                </div>
                            </Row>
                        ))
                    )}
                </Panel>
            </div>

            <Panel
                title={`Today's clinic schedule (${props.todayAppointments.length})`}
                viewAllHref={appointmentsIndex()}
                isTruncated
            >
                {props.todayAppointments.length === 0 ? (
                    <EmptyRow>No appointments today.</EmptyRow>
                ) : (
                    props.todayAppointments.map((appointment) => (
                        <Row key={appointment.id}>
                            <div className="flex min-w-0 items-center gap-3">
                                <span className="w-16 shrink-0 tabular-nums">
                                    {appointment.time}
                                </span>
                                <div className="min-w-0">
                                    <Link
                                        href={showEmployee(
                                            appointment.employee.id,
                                        )}
                                        className="font-medium hover:underline"
                                    >
                                        {appointment.employee.full_name}
                                    </Link>
                                    <p className="text-xs text-muted-foreground">
                                        {optionLabel(
                                            props.appointmentPurposes,
                                            appointment.purpose,
                                        )}
                                    </p>
                                </div>
                            </div>
                            <AppointmentStatusBadge
                                status={appointment.status}
                                options={props.appointmentStatuses}
                            />
                        </Row>
                    ))
                )}
            </Panel>

            <Panel title="By company">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="border-b text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-2 font-medium">
                                    Company
                                </th>
                                <th className="px-4 py-2 text-right font-medium">
                                    Active employees
                                </th>
                                <th className="px-4 py-2 text-right font-medium">
                                    Annual checks overdue
                                </th>
                                <th className="px-4 py-2 text-right font-medium">
                                    Overdue follow-ups
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {props.companies.map((company) => (
                                <tr
                                    key={company.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="px-4 py-2">
                                        <Link
                                            href={employeesIndex({
                                                query: {
                                                    company_id: company.id,
                                                },
                                            })}
                                            className="font-medium hover:underline"
                                        >
                                            {company.name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">
                                        {company.active_employees_count}
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">
                                        {company.annual_overdue_count}
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">
                                        {company.overdue_follow_ups_count}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Panel>
        </>
    );
}

function HrDashboard(props: Extract<Props, { view: 'hr' }>) {
    const { stats, settings } = props;

    return (
        <>
            <PageHeader
                title={`${props.companyName ?? 'Your company'} health compliance`}
                description={`${stats.activeEmployees.toLocaleString()} active employees`}
            />

            <section
                aria-label="Summary"
                className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
            >
                <StatTile
                    label="Overdue follow-ups"
                    value={stats.overdueFollowUps}
                    icon={CalendarX2}
                    tone="critical"
                />
                <StatTile
                    label={`Follow-ups in next ${settings.followUpLookaheadDays} days`}
                    value={stats.upcomingFollowUps}
                    icon={CalendarClock}
                    tone="warning"
                />
                <StatTile
                    label="Annual checks overdue"
                    value={stats.annualChecksOverdue}
                    hint="Includes employees never examined"
                    icon={HeartPulse}
                    tone="critical"
                    href={employeesIndex({ query: { annual: 'overdue' } })}
                />
                <StatTile
                    label={`Annual checks due in ${settings.annualReminderDays} days`}
                    value={stats.annualChecksDueSoon}
                    icon={Users}
                    tone="warning"
                    href={employeesIndex({ query: { annual: 'due' } })}
                />
            </section>

            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                <Lock className="size-4 shrink-0" aria-hidden />
                Follow-up reasons and medical results are confidential and
                visible to medical staff only.
            </p>

            <div className="grid gap-6 xl:grid-cols-2">
                <Panel
                    title="Overdue follow-ups"
                    isTruncated={
                        props.overdueFollowUps.length < stats.overdueFollowUps
                    }
                >
                    <FollowUpRows
                        followUps={props.overdueFollowUps}
                        emptyText="No overdue follow-ups."
                    />
                </Panel>
                <Panel
                    title={`Follow-ups due in the next ${settings.followUpLookaheadDays} days`}
                    isTruncated={
                        props.upcomingFollowUps.length < stats.upcomingFollowUps
                    }
                >
                    <FollowUpRows
                        followUps={props.upcomingFollowUps}
                        emptyText="Nothing due this week."
                    />
                </Panel>
            </div>

            <Panel
                title="Annual checks due"
                description={`Every ${settings.annualIntervalMonths} months. Remind these employees to book their exam with the clinic.`}
                viewAllHref={employeesIndex({ query: { annual: 'due' } })}
                isTruncated={
                    props.annualChecksDue.length <
                    stats.annualChecksOverdue + stats.annualChecksDueSoon
                }
            >
                <AnnualDueRows rows={props.annualChecksDue} />
            </Panel>
        </>
    );
}

function EmployeeDashboard({
    ownRecord,
    settings,
    healthCheckTypes,
    healthCheckResults,
}: {
    ownRecord: OwnRecord | null;
    settings: Settings;
    healthCheckTypes: Option[];
    healthCheckResults: ResultOption[];
}) {
    if (ownRecord === null) {
        return (
            <div className="mx-auto max-w-md py-16 text-center">
                <HeartPulse
                    className="mx-auto size-8 text-muted-foreground"
                    aria-hidden
                />
                <h1 className="mt-4 text-lg font-semibold">
                    Your health record isn&apos;t linked yet
                </h1>
                <p className="mt-2 text-sm text-muted-foreground">
                    Ask your administrator to link your account to your employee
                    record. You&apos;ll then see your checkups and follow-ups
                    here.
                </p>
            </div>
        );
    }

    const { annualCheck, latestCheck, openFollowUps, nextAppointment } =
        ownRecord;

    return (
        <>
            <PageHeader
                title={`Hello, ${ownRecord.employee.full_name.split(', ').reverse().join(' ')}`}
                actions={
                    <>
                        <Button asChild>
                            <Link href={createAppointment()}>
                                Book clinic appointment
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={showEmployee(ownRecord.employee.id)}>
                                View my full health record
                            </Link>
                        </Button>
                    </>
                }
            />

            {nextAppointment && (
                <Link
                    href={appointmentsIndex()}
                    className="flex items-center gap-2 rounded-xl border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900 hover:bg-sky-100 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200"
                >
                    <CalendarDays className="size-4" aria-hidden />
                    Your next clinic appointment:{' '}
                    {formatDateTime(nextAppointment)}
                </Link>
            )}

            <div className="grid gap-6 md:grid-cols-2">
                <Panel
                    title="Annual check"
                    description={`Required every ${settings.annualIntervalMonths} months.`}
                >
                    <div className="space-y-2 px-4 py-3 text-sm">
                        <AnnualCheckBadge summary={annualCheck} />
                        <p>
                            {annualCheck.status === 'never'
                                ? 'You have no annual exam on record. Please book one with the clinic.'
                                : `Last exam ${formatDate(annualCheck.last_checked_on)}. Next due ${formatDate(annualCheck.due_on)}.`}
                        </p>
                    </div>
                </Panel>

                <Panel title="Latest health check">
                    {latestCheck === null ? (
                        <EmptyRow>No health checks yet.</EmptyRow>
                    ) : (
                        <Row>
                            <div>
                                <Link
                                    href={showHealthCheck(latestCheck.id)}
                                    className="font-medium hover:underline"
                                >
                                    {optionLabel(
                                        healthCheckTypes,
                                        latestCheck.type,
                                    )}
                                </Link>
                                <p className="text-xs text-muted-foreground">
                                    {formatDate(latestCheck.checked_on)}
                                </p>
                            </div>
                            <ResultBadge
                                result={latestCheck.result}
                                options={healthCheckResults}
                            />
                        </Row>
                    )}
                </Panel>
            </div>

            <Panel title={`Your follow-ups (${openFollowUps.length})`}>
                {openFollowUps.length === 0 ? (
                    <EmptyRow>You have no follow-ups scheduled.</EmptyRow>
                ) : (
                    openFollowUps.map((followUp) => (
                        <Row key={followUp.id}>
                            <span className="font-medium">
                                {followUp.reason}
                            </span>
                            <span className="text-sm text-muted-foreground">
                                Due {formatDate(followUp.due_on)}
                            </span>
                        </Row>
                    ))
                )}
            </Panel>
        </>
    );
}

function Panel({
    title,
    description,
    viewAllHref,
    isTruncated = false,
    children,
}: {
    title: string;
    description?: string;
    viewAllHref?: Parameters<typeof Link>[0]['href'];
    isTruncated?: boolean;
    children: ReactNode;
}) {
    return (
        <section className="flex flex-col overflow-hidden rounded-xl border border-border bg-card">
            <header className="flex items-start justify-between gap-4 border-b px-4 py-3">
                <div>
                    <h2 className="text-sm font-medium">{title}</h2>
                    {description && (
                        <p className="text-xs text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                {viewAllHref && isTruncated && (
                    <Link
                        href={viewAllHref}
                        className="shrink-0 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                    >
                        View all
                    </Link>
                )}
            </header>
            <div className="divide-y">{children}</div>
        </section>
    );
}

function Row({ children }: { children: ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-4 px-4 py-3 text-sm">
            {children}
        </div>
    );
}

function EmptyRow({ children }: { children: ReactNode }) {
    return (
        <p className="px-4 py-6 text-center text-sm text-muted-foreground">
            {children}
        </p>
    );
}

function FollowUpRows({
    followUps,
    emptyText,
    showDetails = false,
}: {
    followUps: AttentionFollowUp[];
    emptyText: string;
    showDetails?: boolean;
}) {
    if (followUps.length === 0) {
        return <EmptyRow>{emptyText}</EmptyRow>;
    }

    return followUps.map((followUp) => (
        <Row key={followUp.id}>
            <div className="min-w-0">
                <Link
                    href={showEmployee(followUp.employee.id)}
                    className="font-medium hover:underline"
                >
                    {followUp.employee.full_name}
                </Link>
                <p className="truncate text-xs text-muted-foreground">
                    {[
                        followUp.employee.company,
                        followUp.reason,
                        followUp.assignee && `Assigned to ${followUp.assignee}`,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
            </div>
            <div className="flex shrink-0 items-center gap-2">
                <span
                    className={
                        followUp.is_overdue
                            ? 'font-medium text-red-700 dark:text-red-400'
                            : 'text-muted-foreground'
                    }
                >
                    {followUp.is_overdue ? 'Was due ' : 'Due '}
                    {formatDate(followUp.due_on)}
                </span>
                {showDetails && (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={editFollowUp(followUp.id)}>Update</Link>
                    </Button>
                )}
            </div>
        </Row>
    ));
}

function AnnualDueRows({
    rows,
    canRecord = false,
}: {
    rows: AnnualDueRow[];
    canRecord?: boolean;
}) {
    if (rows.length === 0) {
        return <EmptyRow>Everyone is up to date.</EmptyRow>;
    }

    return rows.map((row) => (
        <Row key={row.id}>
            <div className="min-w-0">
                <Link
                    href={showEmployee(row.id)}
                    className="font-medium hover:underline"
                >
                    {row.full_name}
                </Link>
                <p className="text-xs text-muted-foreground">
                    {[
                        row.company,
                        row.annual_check.last_checked_on
                            ? `Last exam ${formatDate(row.annual_check.last_checked_on)}`
                            : 'No exam on record',
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
            </div>
            <div className="flex shrink-0 items-center gap-2">
                <AnnualCheckBadge summary={row.annual_check} />
                <Button variant="ghost" size="sm" asChild>
                    <Link
                        href={createAppointment({
                            query: {
                                employee_id: row.id,
                                purpose: 'annual_check',
                            },
                        })}
                    >
                        Book
                    </Link>
                </Button>
                {canRecord && (
                    <Button variant="outline" size="sm" asChild>
                        <Link
                            href={createHealthCheck({
                                query: { employee_id: row.id },
                            })}
                        >
                            Record
                        </Link>
                    </Button>
                )}
            </div>
        </Row>
    ));
}
