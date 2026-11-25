import { CircleAlert, CircleCheck, Clock } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { formatDate, optionLabel } from '@/lib/medical';
import { cn } from '@/lib/utils';
import type {
    AnnualCheckStatus,
    AnnualCheckSummary,
    Clearance,
    FollowUp,
    Option,
} from '@/types';

const resultTones: Record<string, string> = {
    normal: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    fit: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    fit_with_restrictions:
        'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    abnormal: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    unfit: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    pending:
        'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
};

const statusTones: Record<string, string> = {
    pending: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
    completed:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    missed: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    cancelled:
        'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
};

export function ResultBadge({
    result,
    options,
}: {
    result: string;
    options: Option[];
}) {
    return (
        <Badge className={cn('border-transparent', resultTones[result])}>
            {optionLabel(options, result)}
        </Badge>
    );
}

const clearanceTones: Record<Clearance['value'], string> = {
    cleared:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    restricted:
        'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    not_cleared: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    needs_follow_up:
        'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
    pending:
        'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
};

export function ClearanceBadge({ clearance }: { clearance: Clearance }) {
    return (
        <Badge
            className={cn(
                'border-transparent',
                clearanceTones[clearance.value],
            )}
        >
            {clearance.label}
        </Badge>
    );
}

const annualCheckDisplay: Record<
    AnnualCheckStatus,
    { label: string; icon: LucideIcon; tone: string }
> = {
    never: {
        label: 'Never examined',
        icon: CircleAlert,
        tone: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    },
    overdue: {
        label: 'Overdue',
        icon: CircleAlert,
        tone: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    },
    due_soon: {
        label: 'Due soon',
        icon: Clock,
        tone: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    },
    up_to_date: {
        label: 'Up to date',
        icon: CircleCheck,
        tone: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    },
};

/**
 * Where an employee stands on the annual exam schedule, shown with an icon and words so it never relies on color alone.
 */
export function AnnualCheckBadge({ summary }: { summary: AnnualCheckSummary }) {
    const display = annualCheckDisplay[summary.status];
    const Icon = display.icon;

    return (
        <Badge className={cn('border-transparent', display.tone)}>
            <Icon aria-hidden />
            {display.label}
            {summary.due_on && summary.status !== 'up_to_date'
                ? ` · ${formatDate(summary.due_on)}`
                : ''}
        </Badge>
    );
}

export function FollowUpStatusBadge({
    followUp,
    options,
}: {
    followUp: Pick<FollowUp, 'status' | 'is_overdue'>;
    options: Option[];
}) {
    if (followUp.is_overdue) {
        return (
            <Badge className="border-transparent bg-red-600 text-white dark:bg-red-700">
                Overdue
            </Badge>
        );
    }

    return (
        <Badge
            className={cn('border-transparent', statusTones[followUp.status])}
        >
            {optionLabel(options, followUp.status)}
        </Badge>
    );
}
