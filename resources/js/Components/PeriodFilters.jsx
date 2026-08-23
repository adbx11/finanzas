const monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

/**
 * Selectores Año / Mes opcionales (vacío = sin filtrar ese campo).
 */
export default function PeriodFilters({ year, month, onChange }) {
    const yearBase = year ?? new Date().getFullYear();
    const yearOptions = Array.from({ length: 12 }, (_, i) => yearBase - 6 + i);
    if (year != null && !yearOptions.includes(year)) {
        yearOptions.push(year);
        yearOptions.sort((a, b) => a - b);
    }

    const shift = (delta) => {
        if (year == null) {
            return;
        }
        if (month == null) {
            onChange(year + delta, null);
            return;
        }
        let nextYear = year;
        let nextMonth = month + delta;
        if (nextMonth < 1) {
            nextMonth = 12;
            nextYear -= 1;
        } else if (nextMonth > 12) {
            nextMonth = 1;
            nextYear += 1;
        }
        onChange(nextYear, nextMonth);
    };

    return (
        <div className="flex items-center gap-2">
            <button
                type="button"
                className="px-2 py-1 border rounded disabled:opacity-40"
                disabled={year == null}
                onClick={() => shift(-1)}
            >
                &lt;
            </button>
            <select
                className="rounded border-slate-300"
                value={year ?? ''}
                onChange={(e) => {
                    const v = e.target.value;
                    onChange(v === '' ? null : Number(v), month);
                }}
            >
                <option value="">Año</option>
                {yearOptions.map((y) => (
                    <option key={y} value={y}>{y}</option>
                ))}
            </select>
            <select
                className="rounded border-slate-300"
                value={month ?? ''}
                onChange={(e) => {
                    const v = e.target.value;
                    onChange(year, v === '' ? null : Number(v));
                }}
            >
                <option value="">Mes</option>
                {monthNames.map((name, idx) => (
                    <option key={name} value={idx + 1}>{name}</option>
                ))}
            </select>
            <button
                type="button"
                className="px-2 py-1 border rounded disabled:opacity-40"
                disabled={year == null}
                onClick={() => shift(1)}
            >
                &gt;
            </button>
        </div>
    );
}
