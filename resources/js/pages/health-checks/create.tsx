import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import FormField, { FormSection, PageHeader } from '@/components/form-field';
import HealthCheckFormFields from '@/components/health-check-form-fields';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { todayIso } from '@/lib/medical';
import { create, index, store } from '@/routes/health-checks';
import type {
    EmployeeOption,
    Option,
    ResultOption,
    StaffMember,
} from '@/types';

export default function HealthCheckCreate({
    employees,
    selectedEmployeeId,
    staff,
    healthCheckTypes,
    healthCheckResults,
}: {
    employees: EmployeeOption[];
    selectedEmployeeId: number | null;
    staff: StaffMember[];
    healthCheckTypes: Option[];
    healthCheckResults: ResultOption[];
}) {
    const [scheduleFollowUp, setScheduleFollowUp] = useState(false);
    const [suggestedByResult, setSuggestedByResult] = useState(false);

    return (
        <>
            <Head title="Record health check" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
                <PageHeader title="Record health check" />

                <Form {...store.form()} className="space-y-6">
                    {({ processing, errors, progress }) => (
                        <>
                            <HealthCheckFormFields
                                employees={employees}
                                selectedEmployeeId={selectedEmployeeId}
                                healthCheckTypes={healthCheckTypes}
                                healthCheckResults={healthCheckResults}
                                errors={errors}
                                onResultChange={(result) => {
                                    const needsFollowUp =
                                        result?.needsFollowUp ?? false;
                                    setSuggestedByResult(needsFollowUp);

                                    if (needsFollowUp) {
                                        setScheduleFollowUp(true);
                                    }
                                }}
                            />

                            <FormSection
                                title="Follow-up"
                                description="Schedule a recheck, referral, or lab review based on this exam."
                            >
                                <input
                                    type="hidden"
                                    name="schedule_follow_up"
                                    value={scheduleFollowUp ? '1' : '0'}
                                />
                                <div className="flex items-center gap-3">
                                    <Checkbox
                                        id="schedule_follow_up_toggle"
                                        checked={scheduleFollowUp}
                                        onCheckedChange={(checked) =>
                                            setScheduleFollowUp(
                                                checked === true,
                                            )
                                        }
                                    />
                                    <Label htmlFor="schedule_follow_up_toggle">
                                        Schedule a follow-up
                                    </Label>
                                </div>
                                {suggestedByResult && (
                                    <p className="text-sm text-amber-700 dark:text-amber-400">
                                        This result usually needs a follow-up,
                                        so one has been turned on.
                                    </p>
                                )}

                                {scheduleFollowUp && (
                                    <div className="grid gap-4 md:grid-cols-3">
                                        <FormField
                                            id="follow_up_reason"
                                            label="Reason"
                                            required
                                            error={errors.follow_up_reason}
                                        >
                                            <Input
                                                id="follow_up_reason"
                                                name="follow_up_reason"
                                                placeholder="e.g. Recheck blood pressure"
                                                required
                                            />
                                        </FormField>
                                        <FormField
                                            id="follow_up_due_on"
                                            label="Due date"
                                            required
                                            error={errors.follow_up_due_on}
                                        >
                                            <Input
                                                id="follow_up_due_on"
                                                name="follow_up_due_on"
                                                type="date"
                                                min={todayIso()}
                                                required
                                            />
                                        </FormField>
                                        <FormField
                                            id="follow_up_assigned_to"
                                            label="Assigned to"
                                            error={errors.follow_up_assigned_to}
                                        >
                                            <NativeSelect
                                                id="follow_up_assigned_to"
                                                name="follow_up_assigned_to"
                                                defaultValue=""
                                            >
                                                <option value="">
                                                    Unassigned
                                                </option>
                                                {staff.map((member) => (
                                                    <option
                                                        key={member.id}
                                                        value={member.id}
                                                    >
                                                        {member.name}
                                                    </option>
                                                ))}
                                            </NativeSelect>
                                        </FormField>
                                    </div>
                                )}
                            </FormSection>

                            <FormSection
                                title="Attachments"
                                description="Lab results or scanned forms. PDF, JPG, or PNG, up to 10 MB each, 5 files max."
                            >
                                <Input
                                    id="attachments"
                                    name="attachments[]"
                                    type="file"
                                    multiple
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    aria-label="Attachments"
                                />
                                <InputError
                                    message={
                                        errors.attachments ??
                                        Object.entries(errors).find(([key]) =>
                                            key.startsWith('attachments.'),
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
                            </FormSection>

                            <div className="flex gap-2">
                                <Button disabled={processing}>
                                    Save health check
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

HealthCheckCreate.layout = {
    breadcrumbs: [
        { title: 'Health checks', href: index() },
        { title: 'Record', href: create() },
    ],
};
