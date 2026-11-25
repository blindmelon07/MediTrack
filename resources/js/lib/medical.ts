import type { Option } from '@/types';

/**
 * Format a "YYYY-MM-DD" date for display without shifting it across time zones.
 */
export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const [year, month, day] = value.slice(0, 10).split('-').map(Number);

    return new Date(year, month - 1, day).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

/**
 * Today's date as "YYYY-MM-DD" in the viewer's time zone, for date input defaults.
 */
export function todayIso(): string {
    const now = new Date();
    const offset = now.getTimezoneOffset() * 60_000;

    return new Date(now.getTime() - offset).toISOString().slice(0, 10);
}

/**
 * Look up the label for an enum value.
 */
export function optionLabel(
    options: Option[],
    value: string | null | undefined,
): string {
    return (
        options.find((option) => option.value === value)?.label ?? value ?? '—'
    );
}

/**
 * Format a byte count as a short, human-readable size.
 */
export function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(0)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
