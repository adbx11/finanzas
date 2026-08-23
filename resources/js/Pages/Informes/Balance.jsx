import AdminLayout from '@/Layouts/AdminLayout';
import DateFilterInput, { shiftDate, toDateInputValue } from '@/Components/DateFilterInput';
import { Head, router } from '@inertiajs/react';

function formatMoney(value) {
    return Number(value || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function DateToolbar({ filters, onChange }) {
    const setPreset = (preset) => {
        const today = toDateInputValue();
        if (preset === 'hoy') {
            onChange({ ...filters, desde: today, hasta: today });
        } else if (preset === 'mes') {
            const now = new Date();
            const desde = toDateInputValue(new Date(now.getFullYear(), now.getMonth(), 1));
            const hasta = toDateInputValue(new Date(now.getFullYear(), now.getMonth() + 1, 0));
            onChange({ ...filters, desde, hasta });
        } else if (preset === 'anio') {
            const y = new Date().getFullYear();
            onChange({ ...filters, desde: `${y}-01-01`, hasta: `${y}-12-31` });
        }
    };

    return (
        <div className="flex flex-wrap items-end gap-3 mb-4">
            <div className="flex gap-1">
                <button type="button" className="px-2 py-1 text-sm border rounded hover:bg-slate-50" onClick={() => setPreset('hoy')}>Hoy</button>
                <button type="button" className="px-2 py-1 text-sm border rounded hover:bg-slate-50" onClick={() => setPreset('mes')}>Mes</button>
                <button type="button" className="px-2 py-1 text-sm border rounded hover:bg-slate-50" onClick={() => setPreset('anio')}>Año</button>
            </div>
            <div>
                <label className="block text-xs text-slate-500 mb-1">Desde</label>
                <div className="flex items-center gap-1">
                    <button type="button" className="px-1 border rounded text-sm" onClick={() => onChange({ ...filters, desde: shiftDate(filters.desde, 'd', -1) })}>−d</button>
                    <DateFilterInput
                        value={filters.desde || ''}
                        allowEmpty
                        onCommit={(desde) => onChange({ ...filters, desde })}
                    />
                    <button type="button" className="px-1 border rounded text-sm" onClick={() => onChange({ ...filters, desde: shiftDate(filters.desde, 'd', 1) })}>+d</button>
                </div>
            </div>
            <div>
                <label className="block text-xs text-slate-500 mb-1">Hasta</label>
                <div className="flex items-center gap-1">
                    <button type="button" className="px-1 border rounded text-sm" onClick={() => onChange({ ...filters, hasta: shiftDate(filters.hasta, 'd', -1) })}>−d</button>
                    <DateFilterInput
                        value={filters.hasta || ''}
                        onCommit={(hasta) => onChange({ ...filters, hasta })}
                    />
                    <button type="button" className="px-1 border rounded text-sm" onClick={() => onChange({ ...filters, hasta: shiftDate(filters.hasta, 'd', 1) })}>+d</button>
                </div>
            </div>
        </div>
    );
}

export default function Balance({ balance, filters }) {
    const apply = (next) => {
        router.get(route('informes.balance'), {
            desde: next.desde || undefined,
            hasta: next.hasta,
            con_saldo: next.con_saldo ? 1 : 0,
            revaluar: next.revaluar ? 1 : 0,
        }, { preserveState: true, replace: true });
    };

    const monedas = balance.monedas || [];

    return (
        <AdminLayout header="Balance">
            <Head title="Balance" />

            <DateToolbar filters={filters} onChange={apply} />

            <div className="flex flex-wrap gap-4 mb-4 text-sm">
                <label className="inline-flex items-center gap-2">
                    <input
                        type="checkbox"
                        checked={Boolean(filters.con_saldo)}
                        onChange={(e) => apply({ ...filters, con_saldo: e.target.checked })}
                    />
                    Solo con saldo
                </label>
                <label className="inline-flex items-center gap-2">
                    <input
                        type="checkbox"
                        checked={Boolean(filters.revaluar)}
                        onChange={(e) => apply({ ...filters, revaluar: e.target.checked })}
                    />
                    Revaluar moneda extranjera
                </label>
            </div>

            <div className="bg-white shadow-sm border border-slate-200 overflow-x-auto -mx-4 sm:mx-0 rounded-none sm:rounded-lg border-x-0 sm:border-x">
                <table className="min-w-[720px] w-full text-sm">
                    <thead className="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th className="px-3 py-2 text-right font-medium text-slate-600">Saldo $</th>
                            <th className="px-3 py-2 text-left font-medium text-slate-600">Cuenta</th>
                            {monedas.map((m) => (
                                <th key={m.id} className="px-3 py-2 text-right font-medium text-slate-600">{m.simbolo}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {balance.cuentas.map((row, index) => {
                            const nivel = row.cuenta.nivel ?? 0;
                            const padding = Math.min(nivel, 4) * 16;
                            const saldoPorMoneda = Object.fromEntries(
                                (row.saldos.por_moneda || []).map((p) => [p.id_moneda, p.saldo]),
                            );

                            const base = row.cuenta.imputable
                                ? (index % 2 === 1
                                    ? 'bg-slate-50/80 dark:bg-slate-800/90'
                                    : 'bg-white dark:bg-slate-900')
                                : 'bg-slate-200/70 dark:bg-slate-700';

                            return (
                                <tr key={row.cuenta.id} className={`${base} hover:bg-emerald-50/70 dark:hover:bg-emerald-950/50 transition-colors`}>
                                    <td className="px-3 py-1.5 text-right font-mono whitespace-nowrap dark:text-slate-100">
                                        {formatMoney(row.saldos.saldo)}
                                    </td>
                                    <td className="px-3 py-1.5 whitespace-nowrap dark:text-slate-100" style={{ paddingLeft: `${12 + padding}px` }}>
                                        <span className={row.cuenta.imputable ? '' : 'font-semibold text-slate-700 dark:text-slate-50'}>
                                            {row.cuenta.codigo} {row.cuenta.descripcion}
                                        </span>
                                        {row.cuenta.moneda && !row.cuenta.moneda.local && (
                                            <span className="ml-2 text-xs text-slate-500 dark:text-slate-400">
                                                ({row.cuenta.moneda.simbolo} {formatMoney(row.saldos.saldo_origen)})
                                            </span>
                                        )}
                                    </td>
                                    {monedas.map((m) => (
                                        <td key={m.id} className="px-3 py-1.5 text-right font-mono whitespace-nowrap text-slate-600 dark:text-slate-300">
                                            {saldoPorMoneda[m.id] != null ? formatMoney(saldoPorMoneda[m.id]) : ''}
                                        </td>
                                    ))}
                                </tr>
                            );
                        })}
                        {balance.cuentas.length === 0 && (
                            <tr>
                                <td colSpan={2 + monedas.length} className="px-3 py-8 text-center text-slate-500">
                                    Sin cuentas para mostrar.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
