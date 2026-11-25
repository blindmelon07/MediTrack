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

/**
 * Format a "YYYY-MM-DD HH:mm" clinic time (already in clinic time) for display.
 */
export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const [datePart, timePart = '00:00'] = value.split(' ');
    const [year, month, day] = datePart.split('-').map(Number);
    const [hours, minutes] = timePart.split(':').map(Number);

    return new Date(year, month - 1, day, hours, minutes).toLocaleString(
        undefined,
        {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        },
    );
}

/**
 * Shift a "YYYY-MM-DD" date by whole days.
 */
export function addDays(value: string, days: number): string {
    const [year, month, day] = value.split('-').map(Number);
    const date = new Date(year, month - 1, day + days);

    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}
