/**
 * Formatea una fecha (YYYY-MM-DD o ISO) en locale es-AR sin corrimiento por timezone.
 * `new Date('2026-07-08')` se interpreta como UTC y en AR (UTC-3) muestra el día anterior.
 */
export function formatDateAR(value) {
    if (value == null || value === '') {
        return '';
    }

    const str = String(value);
    const match = str.match(/^(\d{4})-(\d{2})-(\d{2})/);

    if (match) {
        const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
        return date.toLocaleDateString('es-AR');
    }

    const date = new Date(str);
    if (Number.isNaN(date.getTime())) {
        return str;
    }

    return date.toLocaleDateString('es-AR');
}

/**
 * YYYY-MM-DD en calendario local (no usar toISOString: en AR tras 21h ya es el día UTC siguiente).
 */
export function toDateInputValue(date = new Date()) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}
