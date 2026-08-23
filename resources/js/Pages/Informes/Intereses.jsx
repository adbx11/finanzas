import AdminLayout from '@/Layouts/AdminLayout';
import DateFilterInput from '@/Components/DateFilterInput';
import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function formatMoney(value) {
    return Number(value || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function Intereses({ report, filters }) {
    const [cuentasArs, setCuentasArs] = useState(filters.cuentas_ars || '');
    const [cuentasUsd, setCuentasUsd] = useState(filters.cuentas_usd || '');

    useEffect(() => {
        setCuentasArs(filters.cuentas_ars || '');
        setCuentasUsd(filters.cuentas_usd || '');
    }, [filters.cuentas_ars, filters.cuentas_usd]);

    const apply = (next) => {
        router.get(route('informes.intereses'), {
            desde: next.desde,
            hasta: next.hasta,
            cuentas_ars: next.cuentas_ars || undefined,
            cuentas_usd: next.cuentas_usd || undefined,
        }, { preserveState: true, replace: true });
    };

    return (
        <AdminLayout header="Intereses">
            <Head title="Intereses" />

            <div className="flex flex-wrap items-end gap-3 mb-4">
                <div>
                    <label className="block text-xs text-slate-500 mb-1">Desde</label>
                    <DateFilterInput
                        value={filters.desde || ''}
                        onCommit={(desde) => apply({ ...filters, desde, cuentas_ars: cuentasArs, cuentas_usd: cuentasUsd })}
                    />
                </div>
                <div>
                    <label className="block text-xs text-slate-500 mb-1">Hasta</label>
                    <DateFilterInput
                        value={filters.hasta || ''}
                        onCommit={(hasta) => apply({ ...filters, hasta, cuentas_ars: cuentasArs, cuentas_usd: cuentasUsd })}
                    />
                </div>
                <div className="min-w-[14rem] flex-1">
                    <label className="block text-xs text-slate-500 mb-1">Cuentas ARS</label>
                    <input
                        type="text"
                        className="w-full rounded border-slate-300 text-sm font-mono"
                        value={cuentasArs}
                        onChange={(e) => setCuentasArs(e.target.value)}
                        onBlur={() => apply({ ...filters, cuentas_ars: cuentasArs, cuentas_usd: cuentasUsd })}
                    />
                </div>
                <div className="min-w-[14rem] flex-1">
                    <label className="block text-xs text-slate-500 mb-1">Cuentas USD</label>
                    <input
                        type="text"
                        className="w-full rounded border-slate-300 text-sm font-mono"
                        value={cuentasUsd}
                        onChange={(e) => setCuentasUsd(e.target.value)}
                        onBlur={() => apply({ ...filters, cuentas_ars: cuentasArs, cuentas_usd: cuentasUsd })}
                    />
                </div>
            </div>

            <div className="bg-white rounded-lg border border-slate-200 overflow-x-auto shadow-sm">
                <table className="min-w-[640px] w-full text-sm">
                    <thead className="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th className="px-3 py-2 text-left font-medium text-slate-600">Período</th>
                            <th className="px-3 py-2 text-right font-medium text-slate-600">$</th>
                            <th className="px-3 py-2 text-right font-medium text-slate-600">U$D</th>
                            <th className="px-3 py-2 text-right font-medium text-slate-600">Total en $</th>
                            <th className="px-3 py-2 text-right font-medium text-slate-600">Total en U$D</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {(report.filas || []).length === 0 ? (
                            <tr>
                                <td colSpan={5} className="px-3 py-8 text-center text-slate-500">
                                    No hay intereses en el período.
                                </td>
                            </tr>
                        ) : (
                            report.filas.map((fila) => (
                                <tr key={fila.periodo} className="hover:bg-emerald-50/50">
                                    <td className="px-3 py-1.5">{fila.periodo}</td>
                                    <td className="px-3 py-1.5 text-right font-mono tabular-nums">{formatMoney(fila.valor_pesos)}</td>
                                    <td className="px-3 py-1.5 text-right font-mono tabular-nums">{formatMoney(fila.valor_usd)}</td>
                                    <td className="px-3 py-1.5 text-right font-mono tabular-nums">{formatMoney(fila.total_pesos)}</td>
                                    <td className="px-3 py-1.5 text-right font-mono tabular-nums">{formatMoney(fila.total_usd)}</td>
                                </tr>
                            ))
                        )}
                    </tbody>
                    {(report.filas || []).length > 0 && (
                        <tfoot className="border-t border-slate-200 bg-slate-50 font-medium">
                            <tr>
                                <td className="px-3 py-2">Total</td>
                                <td className="px-3 py-2 text-right font-mono tabular-nums">{formatMoney(report.totales.valor_pesos)}</td>
                                <td className="px-3 py-2 text-right font-mono tabular-nums">{formatMoney(report.totales.valor_usd)}</td>
                                <td className="px-3 py-2 text-right font-mono tabular-nums">{formatMoney(report.totales.total_pesos)}</td>
                                <td className="px-3 py-2 text-right font-mono tabular-nums">{formatMoney(report.totales.total_usd)}</td>
                            </tr>
                        </tfoot>
                    )}
                </table>
            </div>
        </AdminLayout>
    );
}
