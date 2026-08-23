import AdminLayout from '@/Layouts/AdminLayout';
import DateFilterInput, { shiftDate, toDateInputValue } from '@/Components/DateFilterInput';
import { Head, router } from '@inertiajs/react';

function formatMoney(value) {
    return Number(value || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function Gastos({ report, filters }) {
    const apply = (next) => {
        router.get(route('informes.gastos'), {
            desde: next.desde,
            hasta: next.hasta,
            zoom: next.zoom,
        }, { preserveState: true, replace: true });
    };

    const setPreset = (preset) => {
        const now = new Date();
        const y = now.getFullYear();
        const m = now.getMonth();
        if (preset === 'hoy') {
            const today = toDateInputValue(now);
            apply({ ...filters, desde: today, hasta: today });
        } else if (preset === 'mes') {
            apply({
                ...filters,
                desde: toDateInputValue(new Date(y, m, 1)),
                hasta: toDateInputValue(new Date(y, m + 1, 0)),
            });
        } else if (preset === 'anio') {
            apply({ ...filters, desde: `${y}-01-01`, hasta: `${y}-12-31` });
        }
    };

    const chartMax = Math.max(...(report.periodos || []).map((p) => Number(p.total || 0)), 0.0001);

    return (
        <AdminLayout header="Gastos">
            <Head title="Gastos" />

            <div className="flex flex-wrap items-end gap-3 mb-4">
                <div className="flex gap-1">
                    <button type="button" className="px-2 py-1 text-sm border rounded hover:bg-slate-50" onClick={() => setPreset('hoy')}>Hoy</button>
                    <button type="button" className="px-2 py-1 text-sm border rounded hover:bg-slate-50" onClick={() => setPreset('mes')}>Mes</button>
                    <button type="button" className="px-2 py-1 text-sm border rounded hover:bg-slate-50" onClick={() => setPreset('anio')}>Año</button>
                </div>
                <div>
                    <label className="block text-xs text-slate-500 mb-1">Zoom</label>
                    <select
                        className="rounded border-slate-300 text-sm"
                        value={filters.zoom}
                        onChange={(e) => apply({ ...filters, zoom: e.target.value })}
                    >
                        <option value="mensual">Mensual</option>
                        <option value="anual">Anual</option>
                        <option value="fecha">Diario</option>
                    </select>
                </div>
                <div>
                    <label className="block text-xs text-slate-500 mb-1">Desde</label>
                    <div className="flex items-center gap-1">
                        <button type="button" className="px-1 border rounded text-sm" onClick={() => apply({ ...filters, desde: shiftDate(filters.desde, 'd', -1) })}>−d</button>
                        <DateFilterInput value={filters.desde || ''} onCommit={(desde) => apply({ ...filters, desde })} />
                        <button type="button" className="px-1 border rounded text-sm" onClick={() => apply({ ...filters, desde: shiftDate(filters.desde, 'd', 1) })}>+d</button>
                    </div>
                </div>
                <div>
                    <label className="block text-xs text-slate-500 mb-1">Hasta</label>
                    <div className="flex items-center gap-1">
                        <button type="button" className="px-1 border rounded text-sm" onClick={() => apply({ ...filters, hasta: shiftDate(filters.hasta, 'd', -1) })}>−d</button>
                        <DateFilterInput value={filters.hasta || ''} onCommit={(hasta) => apply({ ...filters, hasta })} />
                        <button type="button" className="px-1 border rounded text-sm" onClick={() => apply({ ...filters, hasta: shiftDate(filters.hasta, 'd', 1) })}>+d</button>
                    </div>
                </div>
            </div>

            {(report.periodos || []).length > 0 && (
                <div className="bg-white rounded-lg border border-slate-200 p-4 mb-4 shadow-sm">
                    <h3 className="text-sm font-medium text-slate-700 mb-3">Total por período</h3>
                    <div className="flex items-end gap-1 h-40">
                        {report.periodos.map((p) => {
                            const h = Math.max(4, (Number(p.total) / chartMax) * 100);
                            return (
                                <div key={p.periodo} className="flex-1 min-w-0 flex flex-col items-center justify-end h-full gap-1">
                                    <div
                                        className="w-full max-w-[2.5rem] rounded-t bg-emerald-500"
                                        style={{ height: `${h}%` }}
                                        title={`${p.periodo}: $${formatMoney(p.total)}`}
                                    />
                                    <span className="text-[10px] text-slate-500 truncate w-full text-center">{p.periodo}</span>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}

            {(report.periodos || []).length === 0 ? (
                <div className="bg-white border border-slate-200 rounded-lg p-8 text-center text-slate-500">
                    Sin gastos en el período.
                </div>
            ) : (
                <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-3">
                    {report.periodos.map((p) => (
                        <div key={p.periodo} className="bg-white rounded-lg border border-slate-200 p-4 shadow-sm">
                            <div className="flex items-baseline justify-between gap-2 mb-3">
                                <h3 className="text-sm font-medium text-slate-800">{p.periodo}</h3>
                                <span className="text-sm font-mono tabular-nums text-slate-600">${formatMoney(p.total)}</span>
                            </div>
                            <div className="space-y-2">
                                {(p.conceptos || []).length === 0 ? (
                                    <p className="text-sm text-slate-500">Sin conceptos.</p>
                                ) : (
                                    p.conceptos.map((c) => (
                                        <div key={c.cuenta}>
                                            <div className="flex justify-between gap-2 text-xs mb-0.5">
                                                <span className="text-slate-700 truncate" title={c.cuenta}>{c.cuenta}</span>
                                                <span className="font-mono tabular-nums text-slate-500 shrink-0">
                                                    {formatMoney(c.value)} ({c.percent}%)
                                                </span>
                                            </div>
                                            <div className="h-1.5 rounded bg-slate-100 overflow-hidden">
                                                <div
                                                    className="h-full rounded bg-amber-400"
                                                    style={{ width: `${Math.max(2, Math.min(100, Number(c.percent) || 0))}%` }}
                                                />
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
