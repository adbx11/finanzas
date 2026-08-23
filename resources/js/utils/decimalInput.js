/**
 * Parsea un valor de input (AR/US con o sin miles) a string con punto decimal.
 * Ej.: "2.520,90" / "2,520.90" / "2520,90" → "2520.90"
 */
export function parseDecimalInput(value, { thousandsIfThreeDigits = false } = {}) {
    let str = String(value ?? '').trim().replace(/\s/g, '');

    if (str === '' || str === '-' || str === ',' || str === '.') {
        return null;
    }

    const negative = str.startsWith('-');
    if (negative) {
        str = str.slice(1);
    }

    const lastComma = str.lastIndexOf(',');
    const lastDot = str.lastIndexOf('.');

    if (lastComma !== -1 && lastDot !== -1) {
        // Ambos: el último es el separador decimal
        if (lastComma > lastDot) {
            // AR: 2.520,90
            str = str.replace(/\./g, '').replace(',', '.');
        } else {
            // US: 2,520.90
            str = str.replace(/,/g, '');
        }
    } else if (lastComma !== -1) {
        // Solo coma → decimal AR
        str = str.replace(/\./g, '').replace(',', '.');
    } else if (lastDot !== -1) {
        const parts = str.split('.');
        if (parts.length > 2) {
            // Varios puntos → separadores de miles: 1.234.567
            str = parts.join('');
        } else if (
            thousandsIfThreeDigits
            && /^[1-9]\d{0,2}$/.test(parts[0])
            && /^\d{3}$/.test(parts[1] ?? '')
        ) {
            // Solo miles AR en importes: 1.234 → 1234
            str = parts.join('');
        }
        // un solo punto en otro caso: decimal canónico (1.5 / 0.123)
    }

    if (!/^\d+(\.\d+)?$/.test(str)) {
        return null;
    }

    return negative ? `-${str}` : str;
}

/**
 * Formatea un valor numérico para mostrar en input (coma decimal, sin separador de miles).
 */
export function formatDecimalInput(value, decimals = 2) {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const normalized = parseDecimalInput(value, { thousandsIfThreeDigits: decimals <= 2 });

    if (normalized === null || Number.isNaN(Number(normalized))) {
        return sanitizeDecimalInput(String(value), decimals, { allowFormatted: false });
    }

    return Number(normalized).toLocaleString('es-AR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
        useGrouping: false,
    });
}

/**
 * ¿Parece un número ya formateado (p. ej. pegado) con separadores de miles?
 */
function looksLikeFormattedAmount(value, decimals = 2) {
    const str = String(value ?? '').trim();
    if (!str) {
        return false;
    }

    // 2.520,90 / 2,520.90
    if (str.includes('.') && str.includes(',')) {
        return true;
    }

    // 2 520,90 / 2 520.90
    if (/\d\s+\d{3}/.test(str)) {
        return true;
    }

    // 1.234.567 (solo miles)
    if ((str.match(/\./g) || []).length > 1) {
        return true;
    }

    // 1.234 con grupos de miles (solo en importes típicos de 2 decimales)
    if (decimals <= 2 && /^-?[1-9]\d{0,2}(\.\d{3})+$/.test(str.replace(/\s/g, ''))) {
        return true;
    }

    return false;
}

/**
 * Sanitiza el texto mientras el usuario escribe.
 * Si pega un monto formateado con miles, lo normaliza a coma decimal sin miles.
 */
export function sanitizeDecimalInput(raw, decimals = 2, { allowFormatted = true } = {}) {
    const original = String(raw ?? '');

    if (allowFormatted && looksLikeFormattedAmount(original, decimals)) {
        const parsed = parseDecimalInput(original, { thousandsIfThreeDigits: decimals <= 2 });
        if (parsed !== null) {
            return formatDecimalInput(parsed, decimals);
        }
    }

    let value = original.replace(/\./g, ',');

    const negative = value.startsWith('-');
    value = value.replace(/-/g, '');
    value = value.replace(/[^\d,]/g, '');

    const commaIndex = value.indexOf(',');
    if (commaIndex !== -1) {
        const intPart = value.slice(0, commaIndex);
        const decPart = value.slice(commaIndex + 1).replace(/,/g, '').slice(0, decimals);
        value = `${intPart},${decPart}`;
    }

    return negative ? `-${value}` : value;
}
