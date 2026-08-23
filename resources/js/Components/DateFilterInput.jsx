import { useEffect, useRef } from 'react';
import { toDateInputValue } from '@/utils/dateFormat';

/**
 * Date filter for Inertia list/report pages.
 *
 * Uncontrolled: React controlled inputs fight the native picker (month/year
 * navigation fires input with "" and reloads / resets the value).
 * Syncs from `value` when it changes externally (±d, presets, server).
 */
export default function DateFilterInput({
    value,
    onCommit,
    className = 'rounded border-slate-300 text-sm',
    allowEmpty = false,
}) {
    const ref = useRef(null);
    const lastRef = useRef(value || '');
    const onCommitRef = useRef(onCommit);
    onCommitRef.current = onCommit;

    useEffect(() => {
        lastRef.current = value || '';
        if (ref.current && (value || allowEmpty) && ref.current.value !== (value || '')) {
            ref.current.value = value || '';
        }
    }, [value, allowEmpty]);

    return (
        <input
            ref={ref}
            type="date"
            className={className}
            defaultValue={value || ''}
            onChange={(e) => {
                const next = e.target.value;
                if (!next) {
                    if (allowEmpty) {
                        lastRef.current = '';
                        onCommitRef.current(null);
                        return;
                    }
                    e.target.value = lastRef.current;
                    return;
                }
                if (next === lastRef.current) {
                    return;
                }
                lastRef.current = next;
                onCommitRef.current(next);
            }}
        />
    );
}

export { toDateInputValue };

export function shiftDate(dateStr, unit, amount) {
    const d = dateStr ? new Date(`${dateStr}T12:00:00`) : new Date();
    if (unit === 'd') d.setDate(d.getDate() + amount);
    if (unit === 'm') d.setMonth(d.getMonth() + amount);
    if (unit === 'y') d.setFullYear(d.getFullYear() + amount);
    return toDateInputValue(d);
}
