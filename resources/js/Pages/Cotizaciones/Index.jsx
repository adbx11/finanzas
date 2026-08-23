import AdminLayout from '@/Layouts/AdminLayout';
import DateFilterInput from '@/Components/DateFilterInput';
import DecimalInput from '@/Components/DecimalInput';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import { formatDateAR } from '@/utils/dateFormat';
import { formatDecimalInput } from '@/utils/decimalInput';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';

function formatMoney(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }
    return Number(value).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 10 });
}

export default function Index({ fecha, rates, recientes, btcConfig }) {
    const { flash } = usePage().props;

    const form = useForm({
        fecha,
        rates: (rates || []).map((r) => ({
            codigo: r.codigo,
            compra: formatDecimalInput(r.compra, 10) || '',
            venta: formatDecimalInput(r.venta, 10) || '',
        })),
    });

    useEffect(() => {
        form.setData({
            fecha,
            rates: (rates || []).map((r) => ({
                codigo: r.codigo,
                compra: formatDecimalInput(r.compra, 10) || '',
                venta: formatDecimalInput(r.venta, 10) || '',
            })),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [fecha, rates]);

    const changeFecha = (value) => {
        if (!value) {
            return;
        }
        form.setData('fecha', value);
        router.get(route('cotizaciones.index'), { fecha: value }, { preserveState: false, replace: true });
    };

    const setRate = (index, field, value) => {
        const next = form.data.rates.map((row, i) => (i === index ? { ...row, [field]: value } : row));
        form.setData('rates', next);
    };

    const submit = (e) => {
        e.preventDefault();
        form.post(route('cotizaciones.store'), { preserveScroll: true });
    };

    const fetchNow = () => {
        router.post(route('cotizaciones.fetch'), { sync: true }, { preserveScroll: true });
    };

    return (
        <AdminLayout header="Cotizaciones">
            <Head title="Cotizaciones" />

            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{flash.success}</div>
            )}
            {flash?.error && (
                <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{flash.error}</div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                <div className="bg-white rounded-lg border border-slate-200 p-4 shadow-sm">
                    <div className="flex flex-wrap items-end justify-between gap-3 mb-4">
                        <div>
                            <InputLabel value="Fecha" />
                            <div className="mt-1">
                                <DateFilterInput value={form.data.fecha} onCommit={changeFecha} />
                            </div>
                        </div>
                        <PrimaryButton type="button" onClick={fetchNow} className="!bg-emerald-700">
                            Obtener automáticamente
                        </PrimaryButton>
                    </div>

                    <form onSubmit={submit}>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-slate-500 border-b border-slate-100">
                                    <th className="py-2 pr-2 font-medium">Moneda</th>
                                    <th className="py-2 pr-2 font-medium text-right">Compra</th>
                                    <th className="py-2 font-medium text-right">Venta</th>
                                </tr>
                            </thead>
                            <tbody>
                                {form.data.rates.map((row, index) => (
                                    <tr key={row.codigo} className="border-b border-slate-50">
                                        <td className="py-2 pr-2 font-semibold text-slate-800">{row.codigo}</td>
                                        <td className="py-2 pr-2">
                                            <DecimalInput
                                                className="block w-full text-right"
                                                decimals={10}
                                                value={row.compra}
                                                onChange={(v) => setRate(index, 'compra', v)}
                                            />
                                        </td>
                                        <td className="py-2">
                                            <DecimalInput
                                                className="block w-full text-right"
                                                decimals={10}
                                                value={row.venta}
                                                onChange={(v) => setRate(index, 'venta', v)}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <div className="mt-4">
                            <PrimaryButton disabled={form.processing}>Guardar</PrimaryButton>
                        </div>
                    </form>
                </div>

                <div className="bg-white rounded-lg border border-slate-200 p-4 shadow-sm">
                    <h3 className="text-sm font-medium text-slate-700 mb-3">BTC (CoinGecko → configuración)</h3>
                    <div className="space-y-2 text-sm text-slate-600">
                        <div>
                            BTC/USD: <span className="font-mono tabular-nums">{formatMoney(btcConfig?.btcusd)}</span>
                        </div>
                        <div>
                            BTC/ARS: <span className="font-mono tabular-nums">{formatMoney(btcConfig?.btcars)}</span>
                        </div>
                        <p className="text-xs text-slate-400 mt-2">
                            Se actualizan al obtener automáticamente. URLs en Configuración → grupo Cotizaciones.
                        </p>
                    </div>
                </div>
            </div>

            <div className="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
                <div className="px-4 py-2 border-b border-slate-100 bg-slate-50">
                    <h3 className="text-sm font-medium text-slate-700">Últimas cotizaciones</h3>
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left text-slate-500 border-b border-slate-100">
                                <th className="px-4 py-2 font-medium">Fecha</th>
                                <th className="px-4 py-2 font-medium">Moneda</th>
                                <th className="px-4 py-2 font-medium text-right">Compra</th>
                                <th className="px-4 py-2 font-medium text-right">Venta</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(recientes || []).length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="px-4 py-6 text-center text-slate-500">Sin datos.</td>
                                </tr>
                            ) : (
                                recientes.map((row) => (
                                    <tr key={row.id} className="border-b border-slate-50">
                                        <td className="px-4 py-2">{formatDateAR(row.fecha)}</td>
                                        <td className="px-4 py-2 font-medium">{row.codigo}</td>
                                        <td className="px-4 py-2 text-right font-mono tabular-nums">{formatMoney(row.compra)}</td>
                                        <td className="px-4 py-2 text-right font-mono tabular-nums">{formatMoney(row.venta)}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminLayout>
    );
}
