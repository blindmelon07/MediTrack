import { Head, Link } from '@inertiajs/react';
import { CalendarPlus } from 'lucide-react';
import { AppointmentStatusBadge } from '@/components/appointment-badges';
import { PageHeader } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { formatDateTime, optionLabel } from '@/lib/medical';
import { create, index, update } from '@/routes/appointments';
import type { Option, OwnAppointment } from '@/types';

export default function MyAppointments({
    upcoming,
    past,
    canBook,
    purposes,
    statuses,
}: {
    upcoming: OwnAppointment[];
    past: OwnAppointment[];
    canBook: boolean;
    purposes: Option[];
    statuses: Option[];
}) {
    return (
        <>
            <Head title="My appointments" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="My appointments"
                    description="You will get an SMS or email when an appointment is booked, the day before, and if it is cancelled."
                    actions={
                        canBook && (
                            <Button asChild>
                                <Link href={create()}>
                                    <CalendarPlus /> Book appointment
                                </Link>
                            </Button>
                        )
                    }
                />

                <section className="space-y-3">
                    <h2 className="text-base font-medium">Upcoming</h2>
                    {upcoming.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No upcoming appointments.
                        </p>
                    ) : (
                        <ul className="divide-y rounded-xl border border-border bg-card">
                            {upcoming.map((appointment) => (
                                <li
                                    key={appointment.id}
                                    className="flex flex-wrap items-center justify-between gap-2 p-4"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {formatDateTime(
                                                appointment.starts_at,
                                            )}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {optionLabel(
                                                purposes,
                                                appointment.purpose,
                                            )}
                                        </p>
                                    </div>
                                    {appointment.can.cancel && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={update(appointment.id)}
                                                data={{ status: 'cancelled' }}
                                                as="button"
                                                preserveScroll
                                                onBefore={() =>
                                                    confirm(
                                                        'Cancel this appointment?',
                                                    )
                                                }
                                            >
                                                Cancel
                                            </Link>
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {past.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="text-base font-medium">Past</h2>
                        <ul className="divide-y rounded-xl border border-border bg-card">
                            {past.map((appointment) => (
                                <li
                                    key={appointment.id}
                                    className="flex flex-wrap items-center justify-between gap-2 p-4 text-sm"
                                >
                                    <span>
                                        {formatDateTime(appointment.starts_at)}{' '}
                                        ·{' '}
                                        {optionLabel(
                                            purposes,
                                            appointment.purpose,
                                        )}
                                    </span>
                                    <AppointmentStatusBadge
                                        status={appointment.status}
                                        options={statuses}
                                    />
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

MyAppointments.layout = {
    breadcrumbs: [{ title: 'My appointments', href: index() }],
};
