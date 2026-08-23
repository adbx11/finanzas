import AdminLayout from '@/Layouts/AdminLayout';
import DateFilterInput, { shiftDate, toDateInputValue } from '@/Components/DateFilterInput';
import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

function formatMoney(value, decimals = 2) {
    return Number(value || 0).toLocaleString('es-AR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

function SeriesChart({ title, rows, keys, colors, formatValue }) {
    const max = Math.max(
        ...rows.flatMap((r) => keys.map((k) => Math.abs(Number(r[k] || 0)))),
        0.0001,
    );
    const fmt = formatValue || ((v) => formatMoney(v));

    return (
        <div className="bg-white rounded-lg border border-slate-200 p-4 shadow-sm">
            <h3 className="text-sm font-medium text-slate-700 mb-3">{title}</h3>
            {rows.length === 0 ? (
                <p className="text-sm text-slate-500">Sin datos.</p>
            ) : (
                <div className="flex items-end gap-1 h-40">
                    {rows.map((row) => (
                        <div key={row.y} className="flex-1 min-w-0 flex flex-col items-center justify-end h-full gap-0.5">
                            <div className="w-full max-w-[2.5rem] flex flex-col justify-end gap-px h-[85%]">
                                {keys.map((k, i) => {
                                    const v = Math.abs(Number(row[k] || 0));
                                    const h = Math.max(v > 0 ? 3 : 0, (v / max) * 100);
                                    return (
                                        <div
                                            key={k}
                                            className="w-full rounded-t"
                                            style={{ height: `${h}%`, backgroundColor: colors[i % colors.length] }}
                                            title={`${row.y} ${k}: ${fmt(row[k])}`}
                                        />
                                    );
                                })}
                            </div>
                            <span className="text-[9px] text-slate-500 truncate w-full text-center">{row.y}</span>
                        </div>
                    ))}
                </div>
            )}
            <div className="mt-2 flex flex-wrap gap-3 text-[10px] text-slate-500">
                {keys.map((k, i) => (
                    <span key={k} className="inline-flex items-center gap-1">
                        <span className="inline-block h-2 w-2 rounded-sm" style={{ backgroundColor: colors[i % colors.length] }} />
                        {k.replace(/_/g, ' ')}
                    </span>
                ))}
            </div>
        </div>
    );
}

function DataTable({ title, rows, columns }) {
    return (
        <div className="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
            <div className="px-4 py-2 border-b border-slate-100 bg-slate-50">
                <h3 className="text-sm font-medium text-slate-700">{title}</h3>
            </div>
            <div className="overflow-x-auto">
                <table className="min-w-full text-sm">
                    <thead>
                        <tr className="text-left text-slate-500 border-b border-slate-100">
                            {columns.map((c) => (
                                <th key={c.key} className={`px-3 py-2 font-medium ${c.align === 'right' ? 'text-right' : ''}`}>
                                    {c.header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 ? (
                            <tr>
                                <td colSpan={columns.length} className="px-3 py-6 text-center text-slate-500">Sin datos.</td>
                            </tr>
                        ) : (
                            rows.map((row) => (
                                <tr key={row.y} className="border-b border-slate-50">
                                    {columns.map((c) => (
                                        <td
                                            key={c.key}
                                            className={`px-3 py-1.5 ${c.align === 'right' ? 'text-right font-mono tabular-nums' : ''}`}
                                        >
                                            {c.render ? c.render(row) : row[c.key]}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

const COLORS = ['#10b981', '#0ea5e9', '#f59e0b', '#8b5cf6', '#ef4444', '#64748b'];

export default function EvolucionPatrimonial({ report, filters, monedas = [] }) {
    // Draft local para que el value controlado no vuelva atrás a mitad del request.
    const [draft, setDraft] = useState(filters);
    const draftRef = useRef(filters);
    useEffect(() => {
        setDraft(filters);
        draftRef.current = filters;
    }, [filters.desde, filters.hasta, filters.zoom, filters.moneda]);

    const moneda = (draft.moneda || filters.moneda || 'USD').toUpperCase();
    const monedaMeta = monedas.find((m) => m.codigo === moneda) || { codigo: moneda, simbolo: moneda, label: moneda };
    const suffix = `total_${moneda.toLowerCase()}`;
    const decimals = moneda === 'BTC' ? 8 : 2;
    const fmt = (v) => formatMoney(v, decimals);
    // Comparación secundaria: ARS ↔ USD
    const alt = moneda === 'USD' ? 'ARS' : moneda === 'ARS' ? 'USD' : null;
    const altSuffix = alt ? `total_${alt.toLowerCase()}` : null;

    const apply = (next) => {
        const params = {
            desde: next.desde,
            hasta: next.hasta,
            zoom: next.zoom,
            moneda: next.moneda || moneda,
        };
        draftRef.current = params;
        setDraft(params);
        router.get(route('informes.evolucion-patrimonial'), params, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    };

    const setPreset = (preset) => {
        const now = new Date();
        const y = now.getFullYear();
        const m = now.getMonth();
        if (preset === 'hoy') {
            const today = toDateInputValue(now);
            apply({ ...draftRef.current, desde: today, hasta: today, zoom: 'fecha' });
        } else if (preset === 'mes') {
            apply({
                ...draftRef.current,
                desde: toDateInputValue(new Date(y, m, 1)),
                hasta: toDateInputValue(new Date(y, m + 1, 0)),
                zoom: 'mensual',
            });
        } else if (preset === 'anio') {
            apply({ ...draftRef.current, desde: `${y}-01-01`, hasta: `${y}-12-31`, zoom: 'mensual' });
        }
    };

    const valor = report.data_valor || [];
    const variacion = report.data_variacion || [];
    const label = monedaMeta.simbolo || moneda;

    const stockColumns = [
        { key: 'y', header: 'Período' },
        { key: `a_${suffix}`, header: `Activo (${label})`, align: 'right', render: (r) => fmt(r[`a_${suffix}`]) },
        { key: `p_${suffix}`, header: `Pasivo (${label})`, align: 'right', render: (r) => fmt(r[`p_${suffix}`]) },
        {
            key: 'patrimonio',
            header: `Patrimonio (${label})`,
            align: 'right',
            render: (r) => fmt(Number(r[`a_${suffix}`] || 0) - Number(r[`p_${suffix}`] || 0)),
        },
    ];
    if (alt && altSuffix) {
        stockColumns.push(
            { key: `a_${altSuffix}`, header: `Activo (${alt})`, align: 'right', render: (r) => formatMoney(r[`a_${altSuffix}`]) },
            { key: `p_${altSuffix}`, header: `Pasivo (${alt})`, align: 'right', render: (r) => formatMoney(r[`p_${altSuffix}`]) },
            {
                key: 'patrimonio_alt',
                header: `Patrimonio (${alt})`,
                align: 'right',
                render: (r) => formatMoney(Number(r[`a_${altSuffix}`] || 0) - Number(r[`p_${altSuffix}`] || 0)),
            },
        );
    }

    return (
        <AdminLayout header="Evolución patrimonial">
            <Head title="Evolución patrimonial" />

            <div className="flex flex-wrap items-end gap-3 mb-4">
                <div className="flex gap-1">
                    <button type="button" className="px-2 py-1 text-sm border rounded hover:bg-slate-50" onClick={() => setPreset('hoy')}>Hoy</button>
                    <button type="button" className="px-2 py-1 text-sm border rounded hover:bg-slate-50" onClick={() => setPreset('mes')}>Mes</button>
                    <button type="button" className="px-2 py-1 text-sm border rounded hover:bg-slate-50" onClick={() => setPreset('anio')}>Año</button>
                </div>
                <div>
                    <label className="block text-xs text-slate-500 mb-1">Moneda</label>
                    <select
                        className="rounded border-slate-300 text-sm"
                        value={moneda}
                        onChange={(e) => apply({ ...draft, moneda: e.target.value })}
                    >
                        {(monedas.length ? monedas : [
                            { codigo: 'USD', label: 'Dólar (USD)' },
                            { codigo: 'ARS', label: 'Pesos (ARS)' },
                            { codigo: 'EUR', label: 'Euro (EUR)' },
                            { codigo: 'BTC', label: 'Bitcoin (BTC)' },
                        ]).map((m) => (
                            <option key={m.codigo} value={m.codigo}>{m.label || m.codigo}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-xs text-slate-500 mb-1">Zoom</label>
                    <select
                        className="rounded border-slate-300 text-sm"
                        value={draft.zoom}
                        onChange={(e) => apply({ ...draft, zoom: e.target.value })}
                    >
                        <option value="mensual">Mensual</option>
                        <option value="anual">Anual</option>
                        <option value="fecha">Diario</option>
                    </select>
                </div>
                <div>
                    <label className="block text-xs text-slate-500 mb-1">Desde</label>
                    <div className="flex items-center gap-1">
                        <button type="button" className="px-1 border rounded text-sm" onClick={() => apply({ ...draftRef.current, desde: shiftDate(draft.desde, 'd', -1) })}>−d</button>
                        <DateFilterInput
                            className="rounded border-slate-300 text-sm"
                            value={draft.desde || ''}
                            onCommit={(desde) => apply({ ...draftRef.current, desde })}
                        />
                        <button type="button" className="px-1 border rounded text-sm" onClick={() => apply({ ...draftRef.current, desde: shiftDate(draft.desde, 'd', 1) })}>+d</button>
                    </div>
                </div>
                <div>
                    <label className="block text-xs text-slate-500 mb-1">Hasta</label>
                    <div className="flex items-center gap-1">
                        <button type="button" className="px-1 border rounded text-sm" onClick={() => apply({ ...draftRef.current, hasta: shiftDate(draft.hasta, 'd', -1) })}>−d</button>
                        <DateFilterInput
                            className="rounded border-slate-300 text-sm"
                            value={draft.hasta || ''}
                            onCommit={(hasta) => apply({ ...draftRef.current, hasta })}
                        />
                        <button type="button" className="px-1 border rounded text-sm" onClick={() => apply({ ...draftRef.current, hasta: shiftDate(draft.hasta, 'd', 1) })}>+d</button>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-4">
                <SeriesChart
                    title={`Activo (stock en ${label})`}
                    rows={valor}
                    keys={[`a_${suffix}`]}
                    colors={COLORS}
                    formatValue={fmt}
                />
                <SeriesChart
                    title={`Pasivo (stock en ${label})`}
                    rows={valor}
                    keys={[`p_${suffix}`]}
                    colors={['#ef4444']}
                    formatValue={fmt}
                />
                <SeriesChart
                    title={`Variación Activo / Pasivo (${label})`}
                    rows={variacion}
                    keys={[`a_${suffix}`, `p_${suffix}`]}
                    colors={['#10b981', '#ef4444']}
                    formatValue={fmt}
                />
                <SeriesChart
                    title={`Ingresos / Egresos / Diferencia (${label})`}
                    rows={variacion}
                    keys={[`ingresos_${suffix}`, `egresos_${suffix}`, `dif_${suffix}`]}
                    colors={['#0ea5e9', '#f59e0b', '#8b5cf6']}
                    formatValue={fmt}
                />
            </div>

            <div className="space-y-4">
                <DataTable
                    title={`Stock acumulado (${monedaMeta.label || moneda})`}
                    rows={valor}
                    columns={stockColumns}
                />
                <DataTable
                    title={`Flujos del período (${label})`}
                    rows={variacion}
                    columns={[
                        { key: 'y', header: 'Período' },
                        { key: `ingresos_${suffix}`, header: 'Ingresos', align: 'right', render: (r) => fmt(r[`ingresos_${suffix}`]) },
                        { key: `egresos_${suffix}`, header: 'Egresos', align: 'right', render: (r) => fmt(r[`egresos_${suffix}`]) },
                        { key: `dif_${suffix}`, header: 'Dif.', align: 'right', render: (r) => fmt(r[`dif_${suffix}`]) },
                        { key: `a_${suffix}`, header: 'Δ Activo', align: 'right', render: (r) => fmt(r[`a_${suffix}`]) },
                        { key: `p_${suffix}`, header: 'Δ Pasivo', align: 'right', render: (r) => fmt(r[`p_${suffix}`]) },
                    ]}
                />
            </div>
        </AdminLayout>
    );
}
