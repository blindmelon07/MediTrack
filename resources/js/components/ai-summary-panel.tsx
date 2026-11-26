import { HttpResponseError } from "@inertiajs/core";
import { router, useHttp } from "@inertiajs/react";
import { Check, Copy, Sparkles } from "lucide-react";
import { useState } from "react";
import AlertError from "@/components/alert-error";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import { aiSummary } from "@/routes/health-checks";
import { store as storeFollowUps } from "@/routes/health-checks/follow-ups";

type SuggestedFollowUp = { reason: string; due_on: string };

type Draft = { summary: string; follow_ups: SuggestedFollowUp[] };

type EditableFollowUp = SuggestedFollowUp & { selected: boolean };

/**
 * Reads the message the server sends with a failed AI request, if any.
 */
function errorMessage(error: unknown): string {
    if (error instanceof HttpResponseError) {
        try {
            const body = JSON.parse(error.response.data) as {
                message?: string;
            };

            if (body.message) {
                return body.message;
            }
        } catch {
            // Fall through to the generic message.
        }
    }

    return "The AI assistant could not be reached. Please try again.";
}

/**
 * Lets medical staff ask the AI for a draft summary and follow-ups. Nothing is
 * saved until staff choose which follow-ups to schedule.
 */
export default function AiSummaryPanel({
    healthCheckId,
}: {
    healthCheckId: number;
}) {
    const { submit, processing } = useHttp<Record<string, never>, Draft>();
    const [summary, setSummary] = useState<string | null>(null);
    const [followUps, setFollowUps] = useState<EditableFollowUp[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [copied, setCopied] = useState(false);
    const [scheduling, setScheduling] = useState(false);

    async function draft(): Promise<void> {
        setError(null);

        try {
            const reply = await submit(aiSummary(healthCheckId));

            setSummary(reply.summary);
            setFollowUps(
                reply.follow_ups.map((followUp) => ({
                    ...followUp,
                    selected: true,
                })),
            );
        } catch (caught) {
            setError(errorMessage(caught));
        }
    }

    function updateFollowUp(
        index: number,
        changes: Partial<EditableFollowUp>,
    ): void {
        setFollowUps((current) =>
            current.map((followUp, position) =>
                position === index ? { ...followUp, ...changes } : followUp,
            ),
        );
    }

    async function copySummary(): Promise<void> {
        if (summary) {
            await navigator.clipboard.writeText(summary);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        }
    }

    const selected = followUps.filter((followUp) => followUp.selected);

    function schedule(): void {
        router.post(
            storeFollowUps(healthCheckId).url,
            {
                follow_ups: selected.map(({ reason, due_on }) => ({
                    reason,
                    due_on,
                })),
            },
            {
                preserveScroll: true,
                onStart: () => setScheduling(true),
                onFinish: () => setScheduling(false),
                onSuccess: () => setFollowUps([]),
            },
        );
    }

    return (
        <section className="space-y-3 rounded-xl border border-fuchsia-200 bg-fuchsia-50/40 p-4 dark:border-fuchsia-900/60 dark:bg-fuchsia-950/20">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="flex items-center gap-2 text-base font-medium">
                        <Sparkles className="size-4 text-fuchsia-600 dark:text-fuchsia-400" />
                        AI draft
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Sent without the employee's name or contact details.
                        Review before using.
                    </p>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={draft}
                    disabled={processing}
                >
                    {processing ? <Spinner /> : <Sparkles />}
                    {summary === null ? "Draft with AI" : "Draft again"}
                </Button>
            </div>

            {error && <AlertError errors={[error]} title="No draft" />}

            {processing && summary === null && (
                <div className="animate-pulse space-y-2">
                    <div className="h-3 w-full rounded bg-muted" />
                    <div className="h-3 w-5/6 rounded bg-muted" />
                    <div className="h-3 w-2/3 rounded bg-muted" />
                </div>
            )}

            {summary !== null && (
                <div className="space-y-4">
                    <div className="space-y-2">
                        <div className="flex items-center justify-between gap-2">
                            <h3 className="text-sm font-medium">Summary</h3>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={copySummary}
                            >
                                {copied ? <Check /> : <Copy />}
                                {copied ? "Copied" : "Copy"}
                            </Button>
                        </div>
                        <p className="rounded-lg border border-border bg-card p-3 text-sm whitespace-pre-line">
                            {summary}
                        </p>
                    </div>

                    <div className="space-y-2">
                        <h3 className="text-sm font-medium">
                            Suggested follow-ups
                        </h3>
                        {followUps.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No follow-ups suggested.
                            </p>
                        ) : (
                            <>
                                <ul className="space-y-2">
                                    {followUps.map((followUp, index) => (
                                        <li
                                            key={index}
                                            className="flex flex-col gap-2 rounded-lg border border-border bg-card p-3 sm:flex-row sm:items-center"
                                        >
                                            <Checkbox
                                                checked={followUp.selected}
                                                onCheckedChange={(checked) =>
                                                    updateFollowUp(index, {
                                                        selected:
                                                            checked === true,
                                                    })
                                                }
                                                aria-label={`Schedule: ${followUp.reason}`}
                                            />
                                            <Input
                                                value={followUp.reason}
                                                onChange={(event) =>
                                                    updateFollowUp(index, {
                                                        reason: event.target
                                                            .value,
                                                    })
                                                }
                                                aria-label="Reason"
                                                className="flex-1"
                                            />
                                            <Input
                                                type="date"
                                                value={followUp.due_on}
                                                onChange={(event) =>
                                                    updateFollowUp(index, {
                                                        due_on: event.target
                                                            .value,
                                                    })
                                                }
                                                aria-label="Due on"
                                                className="sm:w-44"
                                            />
                                        </li>
                                    ))}
                                </ul>
                                <Button
                                    size="sm"
                                    onClick={schedule}
                                    disabled={
                                        selected.length === 0 || scheduling
                                    }
                                >
                                    {scheduling && <Spinner />}
                                    Schedule {selected.length} selected
                                </Button>
                            </>
                        )}
                    </div>
                </div>
            )}
        </section>
    );
}
