import AdminLayout from '@/Layouts/AdminLayout';
import { formatDecimalInput, parseDecimalInput } from '@/utils/decimalInput';
import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function formatMoney(value) {
    return Number(value || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatQty(value) {
    return Number(value || 0).toLocaleString('es-AR', { maximumFractionDigits: 8 });
}

export default function Crypto({ report, filters, coins }) {
    const [priceUsd, setPriceUsd] = useState(formatDecimalInput(filters.price_usd, 2));

    useEffect(() => {
        setPriceUsd(formatDecimalInput(filters.price_usd, 2));
    }, [filters.price_usd]);

    const apply = (next) => {
        const price = parseDecimalInput(priceUsd) ?? filters.price_usd;
        router.get(route('informes.crypto'), {
            coin: next.coin || 'BTC',
            price_usd: price,
            year_from: next.year_from || undefined,
            year_to: next.year_to || undefined,
        }, { preserveState: true, replace: true });
    };

    const yearNow = new Date().getFullYear();
    const years = Array.from({ length: 15 }, (_, i) => yearNow - 12 + i);

    return (
        <AdminLayout header={`Crypto — ${report.coin}`}>
            <Head title="Informe Crypto" />

            <div className="flex flex-wrap items-end gap-3 mb-4">
                <div>
                    <label className="block text-xs text-slate-500 mb-1">Coin</label>
                    <select
                        className="rounded border-slate-300 text-sm"
                        value={filters.coin || 'BTC'}
                        onChange={(e) => apply({ ...filters, coin: e.target.value })}
                    >
                        {(coins || ['BTC']).map((c) => (
                            <option key={c} value={c}>{c}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-xs text-slate-500 mb-1">Precio valuación USD</label>
                    <input
                        type="text"
                        className="rounded border-slate-300 text-sm text-right w-32"
                        value={priceUsd}
                        onChange={(e) => setPriceUsd(e.target.value)}
                        onBlur={() => apply({ ...filters })}
                    />
                </div>
                <div>
                    <label className="block text-xs text-slate-500 mb-1">Desde año</label>
                    <select
                        className="rounded border-slate-300 text-sm"
                        value={filters.year_from ?? ''}
                        onChange={(e) => apply({
                            ...filters,
                            year_from: e.target.value ? Number(e.target.value) : null,
                        })}
                    >
                        <option value="">—</option>
                        {years.map((y) => (
                            <option key={y} value={y}>{y}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-xs text-slate-500 mb-1">Hasta año</label>
                    <select
                        className="rounded border-slate-300 text-sm"
                        value={filters.year_to ?? ''}
                        onChange={(e) => apply({
                            ...filters,
                            year_to: e.target.value ? Number(e.target.value) : null,
                        })}
                    >
                        <option value="">—</option>
                        {years.map((y) => (
                            <option key={y} value={y}>{y}</option>
                        ))}
                    </select>
                </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                <div className="bg-white rounded-lg border border-slate-200 p-3">
                    <div className="text-xs text-slate-500">Inversión acumulada</div>
                    <div className="font-mono text-lg font-semibold">${formatMoney(report.totales.cumulative_investment_usd)}</div>
                </div>
                <div className="bg-white rounded-lg border border-slate-200 p-3">
                    <div className="text-xs text-slate-500">Cantidad acumulada</div>
                    <div className="font-mono text-lg font-semibold">{formatQty(report.totales.cumulative_quantity)}</div>
                </div>
                <div className="bg-white rounded-lg border border-slate-200 p-3">
                    <div className="text-xs text-slate-500">Valor de mercado</div>
                    <div className="font-mono text-lg font-semibold">${formatMoney(report.totales.cumulative_market_value)}</div>
                </div>
                <div className="bg-white rounded-lg border border-slate-200 p-3">
                    <div className="text-xs text-slate-500">PnL acumulado</div>
                    <div className={`font-mono text-lg font-semibold ${Number(report.totales.cumulative_pnl) >= 0 ? 'text-emerald-700' : 'text-red-600'}`}>
                        ${formatMoney(report.totales.cumulative_pnl)}
                    </div>
                </div>
            </div>

            <div className="bg-white rounded-lg border border-slate-200 overflow-x-auto shadow-sm">
                <table className="min-w-[800px] w-full text-sm">
                    <thead className="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th className="px-3 py-2 text-left font-medium text-slate-600">Año</th>
                            <th className="px-3 py-2 text-right font-medium text-slate-600">Inversión año</th>
                            <th className="px-3 py-2 text-right font-medium text-slate-600">Cant. año</th>
                            <th className="px-3 py-2 text-right font-medium text-slate-600">Inv. acum.</th>
                            <th className="px-3 py-2 text-right font-medium text-slate-600">Cant. acum.</th>
                            <th className="px-3 py-2 text-right font-medium text-slate-600">Valor mercado</th>
                            <th className="px-3 py-2 text-right font-medium text-slate-600">PnL acum.</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {(report.filas || []).length === 0 ? (
                            <tr>
                                <td colSpan={7} className="px-3 py-8 text-center text-slate-500">Sin transacciones.</td>
                            </tr>
                        ) : (
                            report.filas.map((fila) => (
                                <tr key={fila.year} className="hover:bg-emerald-50/50">
                                    <td className="px-3 py-1.5">{fila.year}</td>
                                    <td className="px-3 py-1.5 text-right font-mono tabular-nums">${formatMoney(fila.yearly_investment_usd)}</td>
                                    <td className="px-3 py-1.5 text-right font-mono tabular-nums">{formatQty(fila.yearly_quantity)}</td>
                                    <td className="px-3 py-1.5 text-right font-mono tabular-nums">${formatMoney(fila.cumulative_investment_usd)}</td>
                                    <td className="px-3 py-1.5 text-right font-mono tabular-nums">{formatQty(fila.cumulative_quantity)}</td>
                                    <td className="px-3 py-1.5 text-right font-mono tabular-nums">${formatMoney(fila.cumulative_market_value)}</td>
                                    <td className={`px-3 py-1.5 text-right font-mono tabular-nums ${Number(fila.cumulative_pnl) >= 0 ? 'text-emerald-700' : 'text-red-600'}`}>
                                        ${formatMoney(fila.cumulative_pnl)}
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
