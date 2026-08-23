import AdminLayout from '@/Layouts/AdminLayout';
import DateFilterInput, { shiftDate, toDateInputValue } from '@/Components/DateFilterInput';
import { Head, router } from '@inertiajs/react';

function formatMoney(value) {
    return Number(value || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function DetalleList({ items }) {
    if (!items?.length) {
        return <p className="text-sm text-slate-500">Sin cuotas en este período.</p>;
    }

    const max = Math.max(...items.map((i) => Number(i.total || 0)), 0.0001);

    return (
        <div className="space-y-2">
            {items.map((item) => {
                const width = Math.max(2, Math.min(100, (Number(item.total) / max) * 100));
                return (
                    <div key={item.codigo}>
                        <div className="flex justify-between gap-2 text-xs mb-0.5">
                            <span className="text-slate-700 truncate" title={`${item.codigo} ${item.tarjeta}`}>
                                <span className="font-mono text-slate-400 mr-1">{item.codigo}</span>
                                {item.tarjeta}
                            </span>
                            <span className="font-mono tabular-nums text-slate-500 shrink-0">
                                ${formatMoney(item.total)}
                                <span className="text-slate-400 ml-1">({item.cantidad})</span>
                            </span>
                        </div>
                        <div className="h-1.5 rounded bg-slate-100 overflow-hidden">
                            <div className="h-full rounded bg-rose-400" style={{ width: `${width}%` }} />
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

export default function VencimientosTc({ report, filters }) {
    const apply = (next) => {
        router.get(route('informes.vencimientos-tc'), {
            desde: next.desde,
            hasta: next.hasta,
        }, { preserveState: true, replace: true, preserveScroll: true });
    };

    const setPreset = (preset) => {
        const now = new Date();
        const today = toDateInputValue(now);
        if (preset === '12m') {
            const hasta = new Date(now.getFullYear(), now.getMonth() + 12 + 1, 0);
            apply({ desde: today, hasta: toDateInputValue(hasta) });
        } else if (preset === '6m') {
            const hasta = new Date(now.getFullYear(), now.getMonth() + 6 + 1, 0);
            apply({ desde: today, hasta: toDateInputValue(hasta) });
        } else if (preset === 'anio') {
            apply({
                desde: today,
                hasta: `${now.getFullYear()}-12-31`,
            });
        }
    };

    const chartMax = Math.max(...(report.meses || []).map((m) => Number(m.total || 0)), 0.0001);
    const hasVencidos = Number(report.vencidos?.total || 0) > 0;

    return (
        <AdminLayout header="Vencimientos de TC">
            <Head title="Vencimientos de TC" />

            <p className="text-sm text-slate-500 mb-4">
                Cuotas de tarjeta pendientes (pagos con vencimiento programado).
            </p>

            <div className="flex flex-wrap items-end gap-3 mb-4">
                <div className="flex gap-1">
                    <button type="button" className="px-2 py-1 text-sm border rounded hover:bg-slate-50" onClick={() => setPreset('6m')}>6 meses</button>
                    <button type="button" className="px-2 py-1 text-sm border rounded hover:bg-slate-50" onClick={() => setPreset('12m')}>12 meses</button>
                    <button type="button" className="px-2 py-1 text-sm border rounded hover:bg-slate-50" onClick={() => setPreset('anio')}>Hasta fin de año</button>
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

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
                {(report.horizontes || []).map((h) => (
                    <div key={h.meses} className="bg-white rounded-lg border border-slate-200 p-3 shadow-sm">
                        <div className="text-xs text-slate-500">{h.label}</div>
                        <div className="mt-1 font-mono text-lg font-semibold text-slate-800 tabular-nums">
                            ${formatMoney(h.total)}
                        </div>
                        <div className="text-xs text-slate-400">{h.cantidad} cuota{h.cantidad === 1 ? '' : 's'}</div>
                    </div>
                ))}
                <div className="bg-white rounded-lg border border-slate-200 p-3 shadow-sm">
                    <div className="text-xs text-slate-500">Total del período</div>
                    <div className="mt-1 font-mono text-lg font-semibold text-slate-800 tabular-nums">
                        ${formatMoney(report.total_periodo)}
                    </div>
                    <div className="text-xs text-slate-400">{filters.desde} → {filters.hasta}</div>
                </div>
            </div>

            {hasVencidos && (
                <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    Hay <span className="font-mono font-semibold">${formatMoney(report.vencidos.total)}</span>
                    {' '}en {report.vencidos.cantidad} cuota{report.vencidos.cantidad === 1 ? '' : 's'} con fecha anterior a {filters.desde}
                    {' '}(posiblemente no liquidadas).
                </div>
            )}

            {(report.meses || []).some((m) => Number(m.total) > 0) && (
                <div className="bg-white rounded-lg border border-slate-200 p-4 mb-4 shadow-sm">
                    <h3 className="text-sm font-medium text-slate-700 mb-3">Vencimientos por mes</h3>
                    <div className="flex items-end gap-1 h-40">
                        {report.meses.map((m) => {
                            const h = Number(m.total) > 0 ? Math.max(4, (Number(m.total) / chartMax) * 100) : 0;
                            return (
                                <div key={m.periodo} className="flex-1 min-w-0 flex flex-col items-center justify-end h-full gap-1">
                                    <div
                                        className="w-full max-w-[2.5rem] rounded-t bg-rose-500"
                                        style={{ height: `${h}%` }}
                                        title={`${m.label}: $${formatMoney(m.total)}`}
                                    />
                                    <span className="text-[10px] text-slate-500 truncate w-full text-center">{m.label}</span>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}

            <h2 className="text-sm font-medium text-slate-700 mb-2">Horizontes (detalle por tarjeta)</h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-6">
                {(report.horizontes || []).map((h) => (
                    <div key={`det-${h.meses}`} className="bg-white rounded-lg border border-slate-200 p-4 shadow-sm">
                        <div className="flex items-baseline justify-between gap-2 mb-3">
                            <h3 className="text-sm font-medium text-slate-800">{h.label}</h3>
                            <span className="text-sm font-mono tabular-nums text-slate-600">${formatMoney(h.total)}</span>
                        </div>
                        <DetalleList items={h.detalle} />
                    </div>
                ))}
            </div>

            <h2 className="text-sm font-medium text-slate-700 mb-2">Detalle mensual</h2>
            {(report.meses || []).every((m) => Number(m.total) === 0) ? (
                <div className="bg-white border border-slate-200 rounded-lg p-8 text-center text-slate-500">
                    No hay cuotas de tarjeta con vencimiento en el período.
                </div>
            ) : (
                <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-3">
                    {report.meses.filter((m) => Number(m.total) > 0).map((m) => (
                        <div key={m.periodo} className="bg-white rounded-lg border border-slate-200 p-4 shadow-sm">
                            <div className="flex items-baseline justify-between gap-2 mb-3">
                                <h3 className="text-sm font-medium text-slate-800">{m.label}</h3>
                                <span className="text-sm font-mono tabular-nums text-slate-600">${formatMoney(m.total)}</span>
                            </div>
                            <div className="text-xs text-slate-400 mb-2">{m.cantidad} cuota{m.cantidad === 1 ? '' : 's'}</div>
                            <DetalleList items={m.detalle} />
                        </div>
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
