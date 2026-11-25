import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

type Tone = 'neutral' | 'critical' | 'warning';

const toneStyles: Record<Tone, { icon: string; ring: string }> = {
    neutral: { icon: 'text-muted-foreground', ring: '' },
    critical: {
        icon: 'text-red-600 dark:text-red-400',
        ring: 'border-red-200 dark:border-red-900/70',
    },
    warning: {
        icon: 'text-amber-600 dark:text-amber-400',
        ring: 'border-amber-200 dark:border-amber-900/70',
    },
};

/**
 * A headline number with its label. The tone only applies when the value is
 * non-zero, and the icon plus label carry the meaning, so it never relies on color alone.
 */
export default function StatTile({
    label,
    value,
    hint,
    icon: Icon,
    tone = 'neutral',
    href,
}: {
    label: string;
    value: number;
    hint?: string;
    icon: LucideIcon;
    tone?: Tone;
    href?: NonNullable<InertiaLinkProps['href']>;
}) {
    const activeTone = value > 0 ? tone : 'neutral';
    const content = (
        <>
            <div className="flex min-h-10 items-start justify-between gap-2">
                <span className="text-sm text-muted-foreground">{label}</span>
                <Icon
                    aria-hidden
                    className={cn('size-4', toneStyles[activeTone].icon)}
                />
            </div>
            <div className="text-3xl font-semibold tracking-tight tabular-nums">
                {value.toLocaleString()}
            </div>
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
        </>
    );
    const className = cn(
        'flex flex-col gap-1 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border',
        toneStyles[activeTone].ring,
    );

    if (href) {
        return (
            <Link
                href={href}
                className={cn(className, 'transition-colors hover:bg-muted/40')}
            >
                {content}
            </Link>
        );
    }

    return <div className={className}>{content}</div>;
}
