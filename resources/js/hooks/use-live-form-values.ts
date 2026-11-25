import { useCallback, useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';

/**
 * Mirrors the current values of an uncontrolled form, for live previews.
 * Spread `bind` onto an element that wraps the <form>; input and change events bubble up to it.
 */
export function useLiveFormValues(): {
    values: Record<string, string>;
    bind: {
        ref: React.RefObject<HTMLDivElement | null>;
        onInput: (event: FormEvent) => void;
        onChange: (event: FormEvent) => void;
    };
} {
    const ref = useRef<HTMLDivElement>(null);
    const [values, setValues] = useState<Record<string, string>>({});

    const read = useCallback(() => {
        const form = ref.current?.querySelector('form');

        if (!form) {
            return;
        }

        const next: Record<string, string> = {};

        new FormData(form).forEach((value, key) => {
            if (typeof value === 'string') {
                next[key] = value;
            }
        });
        setValues(next);
    }, []);

    useEffect(() => {
        read();
    }, [read]);

    return { values, bind: { ref, onInput: read, onChange: read } };
}
