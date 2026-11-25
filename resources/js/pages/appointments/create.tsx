import { Form, Head, Link, router } from '@inertiajs/react';
import FormField, { FormSection, PageHeader } from '@/components/form-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, todayIso } from '@/lib/medical';
import { cn } from '@/lib/utils';
import { create, index, store } from '@/routes/appointments';
import type { ClinicSlot, EmployeeOption, Option } from '@/types';

export default function AppointmentCreate({
    employees,
    selectedEmployeeId,
    selectedStartsAt,
    followUp,
    date,
    lastBookableDate,
    slots,
    purposes,
    purpose,
    canAddNotes,
}: {
    employees: EmployeeOption[];
    selectedEmployeeId: number | null;
    selectedStartsAt: string | null;
    followUp: { id: number; due_on: string } | null;
    date: string;
    lastBookableDate: string;
    slots: ClinicSlot[];
    purposes: Option[];
    purpose: string;
    canAddNotes: boolean;
}) {
    const isSelfBooking = employees.length === 1 && selectedEmployeeId !== null;
    const openSlots = slots.filter((slot) => slot.available);

    function changeDate(newDate: string) {
        if (!newDate) {
            return;
        }

        router.reload({
            data: { date: newDate },
            only: ['date', 'slots', 'selectedStartsAt'],
        });
    }

    return (
        <>
            <Head title="Book appointment" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Book clinic appointment"
                    description={
                        followUp
                            ? `For the follow-up due ${formatDate(followUp.due_on)}.`
                            : isSelfBooking
                              ? "You'll get an SMS or email confirmation and a reminder the day before."
                              : 'The employee gets an SMS or email confirmation and a reminder the day before.'
                    }
                />

                <Form {...store.form()} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            {followUp && (
                                <input
                                    type="hidden"
                                    name="follow_up_id"
                                    value={followUp.id}
                                />
                            )}

                            <FormSection title="Who and why">
                                <div className="grid gap-4 md:grid-cols-2">
                                    {isSelfBooking ? (
                                        <input
                                            type="hidden"
                                            name="employee_id"
                                            value={selectedEmployeeId}
                                        />
                                    ) : (
                                        <FormField
                                            id="employee_id"
                                            label="Employee"
                                            required
                                            error={errors.employee_id}
                                        >
                                            <NativeSelect
                                                id="employee_id"
                                                name="employee_id"
                                                required
                                                defaultValue={
                                                    selectedEmployeeId ?? ''
                                                }
                                            >
                                                <option value="" disabled>
                                                    Select an employee
                                                </option>
                                                {employees.map((employee) => (
                                                    <option
                                                        key={employee.id}
                                                        value={employee.id}
                                                    >
                                                        {employee.full_name} (
                                                        {
                                                            employee.employee_number
                                                        }
                                                        )
                                                    </option>
                                                ))}
                                            </NativeSelect>
                                        </FormField>
                                    )}
                                    <FormField
                                        id="purpose"
                                        label="Purpose"
                                        required
                                        error={errors.purpose}
                                    >
                                        <NativeSelect
                                            id="purpose"
                                            name="purpose"
                                            defaultValue={purpose}
                                        >
                                            {purposes.map((option) => (
                                                <option
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </FormField>
                                </div>
                            </FormSection>

                            <FormSection title="When">
                                <FormField
                                    id="date"
                                    label="Date"
                                    className="max-w-xs"
                                >
                                    <Input
                                        id="date"
                                        type="date"
                                        value={date}
                                        min={todayIso()}
                                        max={lastBookableDate}
                                        onChange={(event) =>
                                            changeDate(event.target.value)
                                        }
                                    />
                                </FormField>

                                {slots.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        The clinic is closed on{' '}
                                        {formatDate(date)}. Choose another day.
                                    </p>
                                ) : openSlots.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No open slots left on {formatDate(date)}
                                        . Choose another day.
                                    </p>
                                ) : (
                                    <fieldset className="space-y-2">
                                        <legend className="text-sm font-medium">
                                            Time
                                        </legend>
                                        <div
                                            key={date}
                                            className="grid grid-cols-3 gap-2 sm:grid-cols-5"
                                        >
                                            {slots.map((slot) => (
                                                <label
                                                    key={slot.starts_at}
                                                    className={cn(
                                                        'flex cursor-pointer items-center justify-center rounded-md border px-2 py-2 text-sm tabular-nums transition-colors has-checked:border-primary has-checked:bg-primary has-checked:text-primary-foreground has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50',
                                                        !slot.available &&
                                                            'cursor-not-allowed text-muted-foreground line-through opacity-50',
                                                    )}
                                                >
                                                    <input
                                                        type="radio"
                                                        name="starts_at"
                                                        value={slot.starts_at}
                                                        disabled={
                                                            !slot.available
                                                        }
                                                        defaultChecked={
                                                            slot.starts_at ===
                                                            selectedStartsAt
                                                        }
                                                        className="sr-only"
                                                        required
                                                    />
                                                    {slot.time}
                                                </label>
                                            ))}
                                        </div>
                                    </fieldset>
                                )}
                                <InputError message={errors.starts_at} />
                            </FormSection>

                            {canAddNotes && (
                                <FormSection title="Notes">
                                    <FormField
                                        id="notes"
                                        label="Anything the clinic should know (optional)"
                                        error={errors.notes}
                                    >
                                        <Textarea
                                            id="notes"
                                            name="notes"
                                            rows={3}
                                        />
                                    </FormField>
                                </FormSection>
                            )}

                            <div className="flex gap-2">
                                <Button
                                    disabled={
                                        processing || openSlots.length === 0
                                    }
                                >
                                    Book appointment
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={index()}>Cancel</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

AppointmentCreate.layout = {
    breadcrumbs: [
        { title: 'Appointments', href: index() },
        { title: 'Book', href: create() },
    ],
};
