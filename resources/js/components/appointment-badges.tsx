import { Badge } from '@/components/ui/badge';
import { optionLabel } from '@/lib/medical';
import { cn } from '@/lib/utils';
import type { Option } from '@/types';

const statusTones: Record<string, string> = {
    scheduled: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
    completed:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    no_show: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    cancelled:
        'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
};

export function AppointmentStatusBadge({
    status,
    options,
}: {
    status: string;
    options: Option[];
}) {
    return (
        <Badge className={cn('border-transparent', statusTones[status])}>
            {optionLabel(options, status)}
        </Badge>
    );
}
