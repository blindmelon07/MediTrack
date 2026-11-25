import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Save } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type FormStep = {
    id: string;
    label: string;
    icon: LucideIcon;
    /** Field names on this step, so validation errors can point to it. */
    fields: string[];
    content: ReactNode;
};

/**
 * Splits a long form into tabs with Back / Next / Save. Every step stays rendered
 * (only hidden), so saving submits all fields no matter which tab is open.
 * Use inside an Inertia <Form noValidate>, because the server reports errors per tab.
 */
export default function FormSteps({
    steps,
    errors,
    processing,
    submitLabel = 'Save',
    cancelHref,
}: {
    steps: FormStep[];
    errors: Record<string, string | undefined>;
    processing: boolean;
    submitLabel?: string;
    cancelHref?: NonNullable<InertiaLinkProps['href']>;
}) {
    const [current, setCurrent] = useState(0);
    const panelRef = useRef<HTMLDivElement>(null);

    const stepHasError = (step: FormStep) =>
        Object.keys(errors).some((key) =>
            step.fields.some(
                (field) => key === field || key.startsWith(`${field}.`),
            ),
        );

    const errorKeys = Object.keys(errors).join('|');

    // After a failed save, show the first tab that needs fixing.
    useEffect(() => {
        if (errorKeys === '') {
            return;
        }

        const firstWithError = steps.findIndex(stepHasError);

        if (firstWithError !== -1) {
            setCurrent(firstWithError);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [errorKeys]);

    function goTo(index: number) {
        setCurrent(index);
        panelRef.current?.scrollIntoView({
            block: 'nearest',
            behavior: 'smooth',
        });
    }

    const isFirst = current === 0;
    const isLast = current === steps.length - 1;

    return (
        <div className="space-y-4">
            <div
                role="tablist"
                aria-label="Form sections"
                className="flex flex-wrap gap-2"
            >
                {steps.map((step, index) => {
                    const isActive = index === current;
                    const hasError = stepHasError(step);

                    return (
                        <button
                            key={step.id}
                            type="button"
                            role="tab"
                            id={`step-tab-${step.id}`}
                            aria-selected={isActive}
                            aria-controls={`step-panel-${step.id}`}
                            onClick={() => goTo(index)}
                            className={cn(
                                'relative inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/40',
                                isActive
                                    ? 'border-primary bg-primary text-primary-foreground shadow-sm'
                                    : 'bg-card text-foreground hover:bg-muted',
                            )}
                        >
                            <step.icon className="size-4" aria-hidden />
                            {step.label}
                            {hasError && (
                                <span
                                    className="size-2 rounded-full bg-red-500 ring-2 ring-card"
                                    aria-label="has errors"
                                />
                            )}
                        </button>
                    );
                })}
            </div>

            <div
                ref={panelRef}
                className="overflow-hidden rounded-xl border bg-card shadow-xs"
            >
                {steps.map((step, index) => (
                    <section
                        key={step.id}
                        role="tabpanel"
                        id={`step-panel-${step.id}`}
                        aria-labelledby={`step-tab-${step.id}`}
                        hidden={index !== current}
                        className="space-y-4 p-4 md:p-6"
                    >
                        <h2 className="text-base font-semibold text-primary">
                            {step.label}
                        </h2>
                        {step.content}
                    </section>
                ))}

                <div className="flex flex-wrap items-center justify-between gap-2 border-t bg-muted/30 px-4 py-3 md:px-6">
                    <span className="text-xs text-muted-foreground">
                        Step {current + 1} of {steps.length}
                    </span>
                    <div className="flex flex-wrap gap-2">
                        {cancelHref && (
                            <Button variant="ghost" asChild>
                                <Link href={cancelHref}>Cancel</Link>
                            </Button>
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            disabled={isFirst}
                            onClick={() => goTo(current - 1)}
                        >
                            <ChevronLeft /> Back
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={isLast}
                            onClick={() => goTo(current + 1)}
                        >
                            Next <ChevronRight />
                        </Button>
                        <Button type="submit" disabled={processing}>
                            <Save /> {processing ? 'Saving…' : submitLabel}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}
