import { Form, Head } from '@inertiajs/react';
import { CalendarClock, Paperclip } from 'lucide-react';
import { useState } from 'react';
import DocumentPreviewPane from '@/components/document-preview-pane';
import FormField, { PageHeader } from '@/components/form-field';
import FormSteps from '@/components/form-steps';
import { healthCheckSteps } from '@/components/health-check-form-fields';
import { documentEmployee } from '@/components/health-check-document';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { useLiveFormValues } from '@/hooks/use-live-form-values';
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
    const { values, bind } = useLiveFormValues();

    const employee = employees.find(
        (option) => String(option.id) === values.employee_id,
    );

    return (
        <>
            <Head title="Record health check" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Record health check"
                    description="Fill in each tab. The preview on the right shows the printed examination record and medical certificate."
                />

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)]">
                    <div {...bind}>
                        <Form {...store.form()} noValidate>
                            {({ processing, errors, progress }) => (
                                <FormSteps
                                    errors={errors}
                                    processing={processing}
                                    submitLabel="Save health check"
                                    cancelHref={index()}
                                    steps={[
                                        ...healthCheckSteps({
                                            employees,
                                            selectedEmployeeId,
                                            healthCheckTypes,
                                            healthCheckResults,
                                            errors,
                                            onResultChange: (result) => {
                                                const needsFollowUp =
                                                    result?.needsFollowUp ??
                                                    false;
                                                setSuggestedByResult(
                                                    needsFollowUp,
                                                );

                                                if (needsFollowUp) {
                                                    setScheduleFollowUp(true);
                                                }
                                            },
                                        }),
                                        {
                                            id: 'follow-up',
                                            label: 'Follow-up',
                                            icon: CalendarClock,
                                            fields: [
                                                'follow_up_reason',
                                                'follow_up_due_on',
                                                'follow_up_assigned_to',
                                            ],
                                            content: (
                                                <>
                                                    <p className="text-sm text-muted-foreground">
                                                        Schedule a recheck,
                                                        referral, or lab review
                                                        based on this exam.
                                                    </p>
                                                    <input
                                                        type="hidden"
                                                        name="schedule_follow_up"
                                                        value={
                                                            scheduleFollowUp
                                                                ? '1'
                                                                : '0'
                                                        }
                                                    />
                                                    <div className="flex items-center gap-3">
                                                        <Checkbox
                                                            id="schedule_follow_up_toggle"
                                                            checked={
                                                                scheduleFollowUp
                                                            }
                                                            onCheckedChange={(
                                                                checked,
                                                            ) =>
                                                                setScheduleFollowUp(
                                                                    checked ===
                                                                        true,
                                                                )
                                                            }
                                                        />
                                                        <Label htmlFor="schedule_follow_up_toggle">
                                                            Schedule a follow-up
                                                        </Label>
                                                    </div>
                                                    {suggestedByResult && (
                                                        <p className="text-sm text-amber-700 dark:text-amber-400">
                                                            This result usually
                                                            needs a follow-up,
                                                            so one has been
                                                            turned on.
                                                        </p>
                                                    )}
                                                    {scheduleFollowUp && (
                                                        <div className="grid gap-4 sm:grid-cols-2">
                                                            <FormField
                                                                id="follow_up_reason"
                                                                label="Reason"
                                                                required
                                                                error={
                                                                    errors.follow_up_reason
                                                                }
                                                                className="sm:col-span-2"
                                                            >
                                                                <Input
                                                                    id="follow_up_reason"
                                                                    name="follow_up_reason"
                                                                    placeholder="e.g. Recheck blood pressure"
                                                                />
                                                            </FormField>
                                                            <FormField
                                                                id="follow_up_due_on"
                                                                label="Due date"
                                                                required
                                                                error={
                                                                    errors.follow_up_due_on
                                                                }
                                                            >
                                                                <Input
                                                                    id="follow_up_due_on"
                                                                    name="follow_up_due_on"
                                                                    type="date"
                                                                    min={todayIso()}
                                                                />
                                                            </FormField>
                                                            <FormField
                                                                id="follow_up_assigned_to"
                                                                label="Assigned to"
                                                                error={
                                                                    errors.follow_up_assigned_to
                                                                }
                                                            >
                                                                <NativeSelect
                                                                    id="follow_up_assigned_to"
                                                                    name="follow_up_assigned_to"
                                                                    defaultValue=""
                                                                >
                                                                    <option value="">
                                                                        Unassigned
                                                                    </option>
                                                                    {staff.map(
                                                                        (
                                                                            member,
                                                                        ) => (
                                                                            <option
                                                                                key={
                                                                                    member.id
                                                                                }
                                                                                value={
                                                                                    member.id
                                                                                }
                                                                            >
                                                                                {
                                                                                    member.name
                                                                                }
                                                                            </option>
                                                                        ),
                                                                    )}
                                                                </NativeSelect>
                                                            </FormField>
                                                        </div>
                                                    )}
                                                </>
                                            ),
                                        },
                                        {
                                            id: 'attachments',
                                            label: 'Attachments',
                                            icon: Paperclip,
                                            fields: ['attachments'],
                                            content: (
                                                <>
                                                    <p className="text-sm text-muted-foreground">
                                                        Lab results or scanned
                                                        forms. PDF, JPG, or PNG,
                                                        up to 10 MB each, 5
                                                        files max.
                                                    </p>
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
                                                            Object.entries(
                                                                errors,
                                                            ).find(([key]) =>
                                                                key.startsWith(
                                                                    'attachments.',
                                                                ),
                                                            )?.[1]
                                                        }
                                                    />
                                                    {progress && (
                                                        <progress
                                                            value={
                                                                progress.percentage
                                                            }
                                                            max="100"
                                                            className="w-full"
                                                        />
                                                    )}
                                                </>
                                            ),
                                        },
                                    ]}
                                />
                            )}
                        </Form>
                    </div>

                    <DocumentPreviewPane
                        values={values}
                        employee={documentEmployee(employee)}
                        healthCheckTypes={healthCheckTypes}
                        healthCheckResults={healthCheckResults}
                    />
                </div>
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
