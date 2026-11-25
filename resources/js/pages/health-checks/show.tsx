import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { CalendarPlus, Download, Pencil, Trash2, Upload } from 'lucide-react';
import { PageHeader } from '@/components/form-field';
import InputError from '@/components/input-error';
import { FollowUpStatusBadge, ResultBadge } from '@/components/medical-badges';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDate, formatFileSize, optionLabel } from '@/lib/medical';
import {
    destroy as destroyAttachment,
    show as downloadAttachment,
} from '@/routes/attachments';
import { show as showEmployee } from '@/routes/employees';
import {
    create as createFollowUp,
    edit as editFollowUp,
} from '@/routes/follow-ups';
import { destroy, edit, index, show } from '@/routes/health-checks';
import { store as storeAttachments } from '@/routes/health-checks/attachments';
import type { HealthCheck, Option, ResultOption } from '@/types';

function Detail({
    label,
    value,
}: {
    label: string;
    value: string | number | null | undefined;
}) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm whitespace-pre-line">
                {value === null || value === undefined || value === ''
                    ? '—'
                    : value}
            </dd>
        </div>
    );
}

export default function HealthCheckShow({
    healthCheck,
    healthCheckTypes,
    healthCheckResults,
    followUpStatuses,
}: {
    healthCheck: HealthCheck;
    healthCheckTypes: Option[];
    healthCheckResults: ResultOption[];
    followUpStatuses: Option[];
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Health checks', href: index() },
            {
                title: formatDate(healthCheck.checked_on),
                href: show(healthCheck.id),
            },
        ],
    });

    const employee = healthCheck.employee;
    const followUps = healthCheck.follow_ups ?? [];
    const attachments = healthCheck.attachments ?? [];

    return (
        <>
            <Head title="Health check" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={`${optionLabel(healthCheckTypes, healthCheck.type)} · ${formatDate(healthCheck.checked_on)}`}
                    description={
                        employee && (
                            <Link
                                href={showEmployee(employee.id)}
                                className="hover:underline"
                            >
                                {employee.full_name} ({employee.employee_number}
                                )
                                {employee.department &&
                                    ` · ${employee.department}`}
                            </Link>
                        )
                    }
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={edit(healthCheck.id)}>
                                    <Pencil /> Edit
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link
                                    href={destroy(healthCheck.id)}
                                    as="button"
                                    onBefore={() =>
                                        confirm(
                                            'Delete this health check and its attachments? Its follow-ups will be kept.',
                                        )
                                    }
                                >
                                    <Trash2 /> Delete
                                </Link>
                            </Button>
                        </>
                    }
                />

                <div className="flex items-center gap-3">
                    <span className="text-sm text-muted-foreground">
                        Result
                    </span>
                    <ResultBadge
                        result={healthCheck.result}
                        options={healthCheckResults}
                    />
                </div>

                <section className="space-y-3">
                    <h2 className="text-base font-medium">Vitals</h2>
                    <dl className="grid gap-4 rounded-xl border border-sidebar-border/70 p-4 sm:grid-cols-3 md:grid-cols-6 dark:border-sidebar-border">
                        <Detail
                            label="Blood pressure"
                            value={
                                healthCheck.blood_pressure &&
                                `${healthCheck.blood_pressure} mmHg`
                            }
                        />
                        <Detail
                            label="Heart rate"
                            value={
                                healthCheck.heart_rate &&
                                `${healthCheck.heart_rate} bpm`
                            }
                        />
                        <Detail
                            label="Temperature"
                            value={
                                healthCheck.temperature &&
                                `${healthCheck.temperature} °C`
                            }
                        />
                        <Detail
                            label="Weight"
                            value={
                                healthCheck.weight_kg &&
                                `${healthCheck.weight_kg} kg`
                            }
                        />
                        <Detail
                            label="Height"
                            value={
                                healthCheck.height_cm &&
                                `${healthCheck.height_cm} cm`
                            }
                        />
                        <Detail
                            label="Examined by"
                            value={healthCheck.examined_by}
                        />
                    </dl>
                </section>

                <section className="space-y-3">
                    <h2 className="text-base font-medium">Assessment</h2>
                    <dl className="grid gap-4 rounded-xl border border-sidebar-border/70 p-4 md:grid-cols-3 dark:border-sidebar-border">
                        <Detail label="Findings" value={healthCheck.findings} />
                        <Detail
                            label="Diagnosis"
                            value={healthCheck.diagnosis}
                        />
                        <Detail
                            label="Recommendations"
                            value={healthCheck.recommendations}
                        />
                    </dl>
                    {healthCheck.recorder && (
                        <p className="text-xs text-muted-foreground">
                            Recorded by {healthCheck.recorder.name}
                        </p>
                    )}
                </section>

                <section className="space-y-3">
                    <div className="flex items-center justify-between gap-4">
                        <h2 className="text-base font-medium">
                            Follow-ups ({followUps.length})
                        </h2>
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={createFollowUp({
                                    query: { health_check_id: healthCheck.id },
                                })}
                            >
                                <CalendarPlus /> Schedule follow-up
                            </Link>
                        </Button>
                    </div>
                    {followUps.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No follow-ups for this check.
                        </p>
                    ) : (
                        <ul className="divide-y rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            {followUps.map((followUp) => (
                                <li
                                    key={followUp.id}
                                    className="flex items-center justify-between gap-4 p-4"
                                >
                                    <div className="space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">
                                                {followUp.reason}
                                            </span>
                                            <FollowUpStatusBadge
                                                followUp={followUp}
                                                options={followUpStatuses}
                                            />
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            Due {formatDate(followUp.due_on)}
                                            {followUp.assignee &&
                                                ` · Assigned to ${followUp.assignee.name}`}
                                        </p>
                                    </div>
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={editFollowUp(followUp.id)}>
                                            Update
                                        </Link>
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="space-y-3">
                    <h2 className="text-base font-medium">
                        Attachments ({attachments.length})
                    </h2>
                    {attachments.length > 0 && (
                        <ul className="divide-y rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            {attachments.map((attachment) => (
                                <li
                                    key={attachment.id}
                                    className="flex items-center justify-between gap-4 p-3"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium">
                                            {attachment.original_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {formatFileSize(attachment.size)}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <a
                                                href={downloadAttachment.url(
                                                    attachment.id,
                                                )}
                                            >
                                                <Download /> Download
                                            </a>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={destroyAttachment(
                                                    attachment.id,
                                                )}
                                                as="button"
                                                preserveScroll
                                                onBefore={() =>
                                                    confirm(
                                                        `Delete ${attachment.original_name}?`,
                                                    )
                                                }
                                                aria-label={`Delete ${attachment.original_name}`}
                                            >
                                                <Trash2 />
                                            </Link>
                                        </Button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                    <Form
                        {...storeAttachments.form(healthCheck.id)}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="flex flex-col gap-2 sm:flex-row sm:items-start"
                    >
                        {({ processing, errors, progress }) => (
                            <>
                                <div className="flex-1 space-y-1">
                                    <Input
                                        name="attachments[]"
                                        type="file"
                                        multiple
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        aria-label="Files to upload"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        PDF, JPG, or PNG, up to 10 MB each.
                                    </p>
                                    <InputError
                                        message={
                                            errors.attachments ??
                                            Object.entries(errors).find(
                                                ([key]) =>
                                                    key.startsWith(
                                                        'attachments.',
                                                    ),
                                            )?.[1]
                                        }
                                    />
                                    {progress && (
                                        <progress
                                            value={progress.percentage}
                                            max="100"
                                            className="w-full"
                                        />
                                    )}
                                </div>
                                <Button
                                    variant="secondary"
                                    disabled={processing}
                                >
                                    <Upload /> Upload
                                </Button>
                            </>
                        )}
                    </Form>
                </section>
            </div>
        </>
    );
}
