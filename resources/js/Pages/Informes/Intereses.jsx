import AdminLayout from '@/Layouts/AdminLayout';
import DateFilterInput from '@/Components/DateFilterInput';
import Modal from '@/Components/Modal';
import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function formatMoney(value) {
    return Number(value || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const COLUMN_META = {
    valor_pesos: { label: '$', moneda: 'ARS' },
    valor_usd: { label: 'U$D', moneda: 'USD' },
    total_pesos: { label: 'Total en $', moneda: null },
    total_usd: { label: 'Total en U$D', moneda: null },
};

function filterDesglose(items, moneda) {
    if (!moneda) {
        return items || [];
    }
    return (items || []).filter((item) => item.moneda === moneda);
}

function AmountCell({ value, onClick, disabled }) {
    const formatted = formatMoney(value);
    if (disabled) {
        return <span className="font-mono tabular-nums">{formatted}</span>;
    }
    return (
        <button
            type="button"
            onClick={onClick}
            className="font-mono tabular-nums text-emerald-700 hover:text-emerald-900 hover:underline dark:text-emerald-400 dark:hover:text-emerald-300"
            title="Ver desglose por cuenta"
        >
            {formatted}
        </button>
    );
}

function sumByMoneda(items) {
    return (items || []).reduce(
        (acc, item) => {
            const key = item.moneda === 'USD' ? 'USD' : 'ARS';
            acc[key] += Number(item.importe || 0);
            return acc;
        },
        { ARS: 0, USD: 0 },
    );
}

function DesgloseModal({ open, onClose, title, subtitle, items }) {
    const list = items || [];
    const sums = sumByMoneda(list);
    const monedas = Object.entries(sums).filter(([, value]) => value !== 0);

    return (
        <Modal show={open} onClose={onClose} maxWidth="lg">
            <div className="p-5 dark:bg-slate-900">
                <div className="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <h2 className="text-lg font-medium text-slate-800 dark:text-slate-100">{title}</h2>
                        {subtitle && (
                            <p className="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{subtitle}</p>
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-sm"
                    >
                        Cerrar
                    </button>
                </div>

                {list.length === 0 ? (
                    <p className="text-sm text-slate-500 py-6 text-center">Sin movimientos en cuentas para este importe.</p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
                                <tr>
                                    <th className="px-3 py-2 text-left font-medium text-slate-600 dark:text-slate-300">Cuenta</th>
                                    <th className="px-3 py-2 text-left font-medium text-slate-600 dark:text-slate-300">Nombre</th>
                                    <th className="px-3 py-2 text-left font-medium text-slate-600 dark:text-slate-300">Moneda</th>
                                    <th className="px-3 py-2 text-right font-medium text-slate-600 dark:text-slate-300">Importe</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {list.map((item) => (
                                    <tr key={item.codigo} className="hover:bg-emerald-50/50 dark:hover:bg-slate-800/80">
                                        <td className="px-3 py-1.5 font-mono text-xs">{item.codigo}</td>
                                        <td className="px-3 py-1.5">{item.nombre}</td>
                                        <td className="px-3 py-1.5 text-slate-500">{item.moneda}</td>
                                        <td className="px-3 py-1.5 text-right font-mono tabular-nums">{formatMoney(item.importe)}</td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 font-medium">
                                {monedas.map(([moneda, value]) => (
                                    <tr key={moneda}>
                                        <td colSpan={3} className="px-3 py-2">
                                            Total {moneda}
                                        </td>
                                        <td className="px-3 py-2 text-right font-mono tabular-nums">{formatMoney(value)}</td>
                                    </tr>
                                ))}
                            </tfoot>
                        </table>
                    </div>
                )}
            </div>
        </Modal>
    );
}

export default function Intereses({ report, filters }) {
    const [cuentasArs, setCuentasArs] = useState(filters.cuentas_ars || '');
    const [cuentasUsd, setCuentasUsd] = useState(filters.cuentas_usd || '');
    const [desglose, setDesglose] = useState(null);

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

    const openDesglose = (periodoLabel, column, items) => {
        const meta = COLUMN_META[column];
        const filtered = filterDesglose(items, meta.moneda);
        setDesglose({
            title: `Desglose por cuenta — ${periodoLabel}`,
            subtitle: meta.label,
            items: filtered,
        });
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

            <div className="bg-white rounded-lg border border-slate-200 overflow-x-auto shadow-sm dark:bg-slate-900 dark:border-slate-700">
                <table className="min-w-[640px] w-full text-sm">
                    <thead className="bg-slate-50 border-b border-slate-200 dark:bg-slate-800 dark:border-slate-700">
                        <tr>
                            <th className="px-3 py-2 text-left font-medium text-slate-600 dark:text-slate-300">Período</th>
                            <th className="px-3 py-2 text-right font-medium text-slate-600 dark:text-slate-300">$</th>
                            <th className="px-3 py-2 text-right font-medium text-slate-600 dark:text-slate-300">U$D</th>
                            <th className="px-3 py-2 text-right font-medium text-slate-600 dark:text-slate-300">Total en $</th>
                            <th className="px-3 py-2 text-right font-medium text-slate-600 dark:text-slate-300">Total en U$D</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                        {(report.filas || []).length === 0 ? (
                            <tr>
                                <td colSpan={5} className="px-3 py-8 text-center text-slate-500">
                                    No hay intereses en el período.
                                </td>
                            </tr>
                        ) : (
                            report.filas.map((fila) => (
                                <tr key={fila.periodo} className="hover:bg-emerald-50/50 dark:hover:bg-slate-800/60">
                                    <td className="px-3 py-1.5">{fila.periodo}</td>
                                    {['valor_pesos', 'valor_usd', 'total_pesos', 'total_usd'].map((column) => (
                                        <td key={column} className="px-3 py-1.5 text-right">
                                            <AmountCell
                                                value={fila[column]}
                                                disabled={!fila.desglose?.length}
                                                onClick={() => openDesglose(fila.periodo, column, fila.desglose)}
                                            />
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </tbody>
                    {(report.filas || []).length > 0 && (
                        <tfoot className="border-t border-slate-200 bg-slate-50 font-medium dark:border-slate-700 dark:bg-slate-800">
                            <tr>
                                <td className="px-3 py-2">Total</td>
                                {['valor_pesos', 'valor_usd', 'total_pesos', 'total_usd'].map((column) => (
                                    <td key={column} className="px-3 py-2 text-right">
                                        <AmountCell
                                            value={report.totales[column]}
                                            disabled={!(report.totales_desglose || []).length}
                                            onClick={() => openDesglose('Total', column, report.totales_desglose)}
                                        />
                                    </td>
                                ))}
                            </tr>
                        </tfoot>
                    )}
                </table>
            </div>

            <DesgloseModal
                open={Boolean(desglose)}
                onClose={() => setDesglose(null)}
                title={desglose?.title}
                subtitle={desglose?.subtitle}
                items={desglose?.items}
            />
        </AdminLayout>
    );
}
