import AdminLayout from '@/Layouts/AdminLayout';
import CuentaSelect from '@/Components/CuentaSelect';
import DateFilterInput, { shiftDate, toDateInputValue } from '@/Components/DateFilterInput';
import { Head, router } from '@inertiajs/react';

function formatMoney(value) {
    return Number(value || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function SeriesChart({ title, rows, valueKey = 'a', color = '#10b981' }) {
    const max = Math.max(...rows.map((r) => Math.abs(Number(r[valueKey] || 0))), 0.0001);

    return (
        <div className="bg-white rounded-lg border border-slate-200 p-4 shadow-sm">
            <h3 className="text-sm font-medium text-slate-700 mb-3">{title}</h3>
            {rows.length === 0 ? (
                <p className="text-sm text-slate-500">Sin datos.</p>
            ) : (
                <div className="flex items-end gap-1 h-40">
                    {rows.map((row) => {
                        const v = Math.abs(Number(row[valueKey] || 0));
                        const h = Math.max(v > 0 ? 4 : 0, (v / max) * 100);
                        const negative = Number(row[valueKey] || 0) < 0;
                        return (
                            <div key={row.y} className="flex-1 min-w-0 flex flex-col items-center justify-end h-full gap-1">
                                <div
                                    className="w-full max-w-[2.5rem] rounded-t"
                                    style={{ height: `${h}%`, backgroundColor: negative ? '#ef4444' : color }}
                                    title={`${row.y}: ${formatMoney(row[valueKey])}`}
                                />
                                <span className="text-[9px] text-slate-500 truncate w-full text-center">{row.y}</span>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

export default function EvolucionCuenta({ report, cuentas, filters }) {
    const apply = (next) => {
        router.get(route('informes.evolucion-cuenta'), {
            id_cuenta: next.id_cuenta || undefined,
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
            apply({ ...filters, desde: today, hasta: today, zoom: 'fecha' });
        } else if (preset === 'mes') {
            apply({
                ...filters,
                desde: toDateInputValue(new Date(y, m, 1)),
                hasta: toDateInputValue(new Date(y, m + 1, 0)),
                zoom: 'mensual',
            });
        } else if (preset === 'anio') {
            apply({ ...filters, desde: `${y}-01-01`, hasta: `${y}-12-31`, zoom: 'mensual' });
        }
    };

    const valor = report?.data_valor || [];
    const variacion = report?.data_variacion || [];

    return (
        <AdminLayout header="Evolución por cuenta">
            <Head title="Evolución por cuenta" />

            <div className="flex flex-wrap items-end gap-3 mb-4">
                <div className="min-w-[16rem] flex-1">
                    <label className="block text-xs text-slate-500 mb-1">Cuenta</label>
                    <CuentaSelect
                        cuentas={cuentas}
                        value={filters.id_cuenta || ''}
                        onChange={(id) => apply({ ...filters, id_cuenta: id })}
                        placeholder="Seleccionar cuenta..."
                    />
                </div>
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

            {!report ? (
                <div className="bg-white border border-slate-200 rounded-lg p-8 text-center text-slate-500">
                    Seleccioná una cuenta para ver la evolución.
                </div>
            ) : (
                <div className="space-y-4">
                    <div className="text-sm text-slate-600">
                        <span className="font-medium text-slate-800">
                            {report.cuenta.codigo} {report.cuenta.descripcion}
                        </span>
                        <span className="ml-2">({report.moneda})</span>
                    </div>

                    <div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
                        <SeriesChart title="Saldo acumulado" rows={valor} valueKey="a" color="#10b981" />
                        <SeriesChart title="Variación del período" rows={variacion} valueKey="a" color="#0ea5e9" />
                        <SeriesChart title="Debe" rows={variacion} valueKey="debe" color="#f59e0b" />
                        <SeriesChart title="Haber" rows={variacion} valueKey="haber" color="#8b5cf6" />
                    </div>

                    <div className="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
                        <div className="px-4 py-2 border-b border-slate-100 bg-slate-50">
                            <h3 className="text-sm font-medium text-slate-700">Detalle por período</h3>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="text-left text-slate-500 border-b border-slate-100">
                                        <th className="px-3 py-2 font-medium">Período</th>
                                        <th className="px-3 py-2 font-medium text-right">Saldo</th>
                                        <th className="px-3 py-2 font-medium text-right">Variación</th>
                                        <th className="px-3 py-2 font-medium text-right">Debe</th>
                                        <th className="px-3 py-2 font-medium text-right">Haber</th>
                                        <th className="px-3 py-2 font-medium text-right">% acum.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {valor.map((row, i) => (
                                        <tr key={row.y} className="border-b border-slate-50">
                                            <td className="px-3 py-1.5">{row.y}</td>
                                            <td className="px-3 py-1.5 text-right font-mono tabular-nums">{formatMoney(row.a)}</td>
                                            <td className="px-3 py-1.5 text-right font-mono tabular-nums">{formatMoney(variacion[i]?.a)}</td>
                                            <td className="px-3 py-1.5 text-right font-mono tabular-nums">{formatMoney(variacion[i]?.debe)}</td>
                                            <td className="px-3 py-1.5 text-right font-mono tabular-nums">{formatMoney(variacion[i]?.haber)}</td>
                                            <td className="px-3 py-1.5 text-right font-mono tabular-nums">
                                                {formatMoney(report.data_variacion_acumulada?.[i]?.a)}%
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
