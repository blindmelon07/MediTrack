import { Form, Head, Link } from '@inertiajs/react';
import { CalendarPlus, ChevronLeft, ChevronRight } from 'lucide-react';
import { AppointmentStatusBadge } from '@/components/appointment-badges';
import { PageHeader } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { addDays, formatDate, optionLabel, todayIso } from '@/lib/medical';
import { create, index, update } from '@/routes/appointments';
import { show as showEmployee } from '@/routes/employees';
import type { Option, ScheduleEntry, ScheduleSlot } from '@/types';

export default function AppointmentIndex({
    date,
    schedule,
    isClinicOpen,
    totals,
    purposes,
    statuses,
}: {
    date: string;
    schedule: ScheduleSlot[];
    isClinicOpen: boolean;
    totals: { scheduled: number; completed: number; available: number };
    purposes: Option[];
    statuses: Option[];
}) {
    const isToday = date === todayIso();

    return (
        <>
            <Head title="Clinic schedule" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Clinic schedule"
                    description={
                        isClinicOpen
                            ? `${formatDate(date)}${isToday ? ' (today)' : ''} · ${totals.scheduled} scheduled, ${totals.completed} completed, ${totals.available} slots open`
                            : formatDate(date)
                    }
                    actions={
                        <Button asChild>
                            <Link href={create({ query: { date } })}>
                                <CalendarPlus /> Book appointment
                            </Link>
                        </Button>
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" size="sm" asChild>
                        <Link
                            href={index({ query: { date: addDays(date, -1) } })}
                            aria-label="Previous day"
                        >
                            <ChevronLeft />
                        </Link>
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        asChild
                        disabled={isToday}
                    >
                        <Link href={index({ query: { date: todayIso() } })}>
                            Today
                        </Link>
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                        <Link
                            href={index({ query: { date: addDays(date, 1) } })}
                            aria-label="Next day"
                        >
                            <ChevronRight />
                        </Link>
                    </Button>
                    <Form {...index.form()} className="flex items-center gap-2">
                        <Input
                            type="date"
                            name="date"
                            defaultValue={date}
                            aria-label="Go to date"
                            className="w-44"
                            onChange={(event) =>
                                event.currentTarget.form?.requestSubmit()
                            }
                        />
                    </Form>
                </div>

                {!isClinicOpen ? (
                    <p className="rounded-xl border border-border bg-card p-6 text-center text-sm text-muted-foreground">
                        The clinic is closed on this day.
                    </p>
                ) : (
                    <ol className="divide-y rounded-xl border border-border bg-card">
                        {schedule.map((slot) => (
                            <li
                                key={slot.starts_at}
                                className="flex flex-col gap-2 p-3 sm:flex-row sm:items-start"
                            >
                                <div className="w-24 shrink-0 pt-1 text-sm font-medium tabular-nums">
                                    {slot.time}
                                </div>
                                <div className="flex flex-1 flex-col gap-2">
                                    {slot.appointments.map((entry) => (
                                        <ScheduleRow
                                            key={entry.id}
                                            entry={entry}
                                            purposes={purposes}
                                            statuses={statuses}
                                        />
                                    ))}
                                    {slot.available && (
                                        <div className="flex items-center justify-between gap-2 text-sm">
                                            <span className="text-muted-foreground">
                                                Open
                                            </span>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={create({
                                                        query: {
                                                            date,
                                                            starts_at:
                                                                slot.starts_at,
                                                        },
                                                    })}
                                                >
                                                    Book
                                                </Link>
                                            </Button>
                                        </div>
                                    )}
                                    {!slot.available &&
                                        slot.appointments.length === 0 && (
                                            <span className="pt-1 text-sm text-muted-foreground">
                                                Past
                                            </span>
                                        )}
                                </div>
                            </li>
                        ))}
                    </ol>
                )}
            </div>
        </>
    );
}

function ScheduleRow({
    entry,
    purposes,
    statuses,
}: {
    entry: ScheduleEntry;
    purposes: Option[];
    statuses: Option[];
}) {
    if (entry.employee === null) {
        return (
            <div className="flex items-center justify-between gap-2 rounded-lg bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                Booked (another company)
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-2 rounded-lg bg-muted/40 px-3 py-2 text-sm sm:flex-row sm:items-center sm:justify-between">
            <div className="min-w-0 space-y-0.5">
                <div className="flex flex-wrap items-center gap-2">
                    <Link
                        href={showEmployee(entry.employee.id)}
                        className="font-medium hover:underline"
                    >
                        {entry.employee.full_name}
                    </Link>
                    <AppointmentStatusBadge
                        status={entry.status}
                        options={statuses}
                    />
                </div>
                <p className="text-xs text-muted-foreground">
                    {[
                        entry.employee.company,
                        optionLabel(purposes, entry.purpose),
                        entry.booked_by && `Booked by ${entry.booked_by}`,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
                {entry.notes && <p className="text-xs">{entry.notes}</p>}
            </div>
            <div className="flex shrink-0 flex-wrap gap-2">
                {entry.can.recordOutcome && (
                    <>
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={update(entry.id)}
                                data={{ status: 'completed' }}
                                as="button"
                                preserveScroll
                            >
                                Completed
                            </Link>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={update(entry.id)}
                                data={{ status: 'no_show' }}
                                as="button"
                                preserveScroll
                            >
                                No-show
                            </Link>
                        </Button>
                    </>
                )}
                {entry.can.cancel && (
                    <Button variant="ghost" size="sm" asChild>
                        <Link
                            href={update(entry.id)}
                            data={{ status: 'cancelled' }}
                            as="button"
                            preserveScroll
                            onBefore={() =>
                                confirm(
                                    `Cancel ${entry.employee?.full_name}'s appointment? They will be notified.`,
                                )
                            }
                        >
                            Cancel
                        </Link>
                    </Button>
                )}
            </div>
        </div>
    );
}

AppointmentIndex.layout = {
    breadcrumbs: [{ title: 'Clinic schedule', href: index() }],
};
