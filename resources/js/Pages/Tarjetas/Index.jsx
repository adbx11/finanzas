import AdminLayout from '@/Layouts/AdminLayout';
import CuentaSelect from '@/Components/CuentaSelect';
import DecimalInput from '@/Components/DecimalInput';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { formatDecimalInput, parseDecimalInput } from '@/utils/decimalInput';
import { toDateInputValue } from '@/utils/dateFormat';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Save } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

const monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

function formatMoney(value) {
    return Number(value || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function toNumber(value) {
    const parsed = parseDecimalInput(value);
    if (parsed === null || parsed === '') {
        return 0;
    }
    return Number(parsed);
}

function TarjetaCard({ tarjeta, cuentasOrigen, year, month, onTotalsChange }) {
    const form = useForm({
        id_cuenta: tarjeta.id_cuenta,
        id_cuenta_origen: tarjeta.id_cuenta_origen || '',
        fecha: tarjeta.fecha || toDateInputValue(),
        importe: formatDecimalInput(tarjeta.total, 2),
        year,
        month,
    });

    const totalBase = toNumber(formatDecimalInput(tarjeta.total, 2));
    const importeNum = toNumber(form.data.importe);
    const diferencia = Number((importeNum - totalBase).toFixed(2));
    const today = toDateInputValue();
    const pagada = form.data.fecha && form.data.fecha <= today;

    useEffect(() => {
        onTotalsChange?.(tarjeta.id_cuenta, {
            importe: importeNum,
            pendiente: form.data.fecha && form.data.fecha > today,
        });
    }, [tarjeta.id_cuenta, importeNum, form.data.fecha]);

    const submit = (e) => {
        e.preventDefault();
        form.post(route('tarjetas.liquidar'), { preserveScroll: true });
    };

    return (
        <form
            onSubmit={submit}
            className={`rounded-lg border-2 p-4 space-y-3 ${
                pagada
                    ? 'border-emerald-500 bg-emerald-50/80 dark:border-emerald-600 dark:bg-emerald-950/70'
                    : 'border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900'
            }`}
        >
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <h3 className="font-semibold text-slate-800 text-base leading-tight">
                        {tarjeta.descripcion}
                    </h3>
                </div>
                <div>
                    <InputLabel value="Fecha de pago" />
                    <input
                        type="date"
                        className="mt-1 block w-full rounded-md border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                        value={form.data.fecha}
                        onChange={(e) => form.setData('fecha', e.target.value)}
                    />
                    <InputError message={form.errors.fecha} />
                </div>
                <div>
                    <InputLabel value="Forma de pago" />
                    <div className="mt-1">
                        <CuentaSelect
                            cuentas={cuentasOrigen}
                            value={form.data.id_cuenta_origen}
                            onChange={(id) => form.setData('id_cuenta_origen', id)}
                            placeholder="Seleccionar cuenta..."
                        />
                    </div>
                    <InputError message={form.errors.id_cuenta_origen} />
                </div>
                <div>
                    <InputLabel value="Total" />
                    <div className="mt-1 flex items-stretch gap-2">
                        <DecimalInput
                            className="block w-full text-sm text-right"
                            decimals={2}
                            value={form.data.importe}
                            onChange={(value) => form.setData('importe', value)}
                        />
                        <button
                            type="submit"
                            title="Guardar"
                            aria-label="Guardar"
                            disabled={form.processing || !form.data.id_cuenta_origen}
                            className="inline-flex items-center justify-center rounded-md bg-emerald-600 px-2.5 text-white hover:bg-emerald-700 disabled:opacity-40 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
                        >
                            <Save className="h-4 w-4" strokeWidth={2} />
                        </button>
                    </div>
                    <InputError message={form.errors.importe} />
                    {diferencia !== 0 && (
                        <p className="mt-1 text-xs text-amber-700 text-right">
                            Diferencia (otros conceptos): {formatMoney(diferencia)}
                        </p>
                    )}
                </div>
            </div>

            <div className="border-t border-slate-100 pt-2">
                <table className="w-full text-sm">
                    <tbody>
                        {tarjeta.movimientos.map((mov) => (
                            <tr key={mov.id} className="border-b border-dotted border-slate-100">
                                <td className="py-1.5 pr-2 text-slate-700">
                                    <span className="inline-flex items-start gap-1.5">
                                        {mov.cuota_completa && (
                                            <CheckCircle2 className="h-4 w-4 text-emerald-600 shrink-0 mt-0.5" strokeWidth={2} />
                                        )}
                                        <span>{mov.comentarios || '—'}</span>
                                    </span>
                                </td>
                                <td className="py-1.5 text-right font-mono whitespace-nowrap tabular-nums">
                                    {formatMoney(mov.importe)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </form>
    );
}

export default function Index({ year, month, total, saldo, tarjetas, cuentasOrigen }) {
    const { flash } = usePage().props;
    const [cards, setCards] = useState(tarjetas);
    const [liveTotals, setLiveTotals] = useState({});

    useEffect(() => {
        setCards(tarjetas);
        setLiveTotals({});
    }, [tarjetas]);

    const onTotalsChange = (idCuenta, data) => {
        setLiveTotals((prev) => {
            const cur = prev[idCuenta];
            if (cur && cur.importe === data.importe && cur.pendiente === data.pendiente) {
                return prev;
            }
            return { ...prev, [idCuenta]: data };
        });
    };

    const headerTotal = useMemo(() => {
        const ids = cards.map((c) => c.id_cuenta);
        if (ids.some((id) => liveTotals[id] == null)) {
            return toNumber(total);
        }
        return ids.reduce((sum, id) => sum + (liveTotals[id]?.importe || 0), 0);
    }, [cards, liveTotals, total]);

    const headerSaldo = useMemo(() => {
        const ids = cards.map((c) => c.id_cuenta);
        if (ids.some((id) => liveTotals[id] == null)) {
            return toNumber(saldo);
        }
        return ids.reduce((sum, id) => {
            const row = liveTotals[id];
            return sum + (row?.pendiente ? row.importe : 0);
        }, 0);
    }, [cards, liveTotals, saldo]);

    const changePeriod = (y, m) => {
        router.get(route('tarjetas.index'), { year: y, month: m }, { preserveState: false, replace: true });
    };

    const shift = (delta) => {
        let nextYear = year;
        let nextMonth = month + delta;
        if (nextMonth < 1) {
            nextMonth = 12;
            nextYear -= 1;
        } else if (nextMonth > 12) {
            nextMonth = 1;
            nextYear += 1;
        }
        changePeriod(nextYear, nextMonth);
    };

    const yearOptions = useMemo(() => {
        const base = year || new Date().getFullYear();
        return Array.from({ length: 7 }, (_, i) => base - 3 + i);
    }, [year]);

    return (
        <AdminLayout header={`Tarjetas — Total: $${formatMoney(headerTotal)} — Saldo: $${formatMoney(headerSaldo)}`}>
            <Head title="Tarjetas" />

            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {flash.success}
                </div>
            )}
            {flash?.error && (
                <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                    {flash.error}
                </div>
            )}

            <div className="flex flex-wrap items-center gap-2 mb-4">
                <button type="button" className="px-2 py-1 border rounded" onClick={() => shift(-1)}>&lt;</button>
                <select
                    className="rounded border-slate-300 text-sm"
                    value={year}
                    onChange={(e) => changePeriod(Number(e.target.value), month)}
                >
                    {yearOptions.map((y) => (
                        <option key={y} value={y}>{y}</option>
                    ))}
                </select>
                <select
                    className="rounded border-slate-300 text-sm"
                    value={month}
                    onChange={(e) => changePeriod(year, Number(e.target.value))}
                >
                    {monthNames.map((name, idx) => (
                        <option key={name} value={idx + 1}>{name}</option>
                    ))}
                </select>
                <button type="button" className="px-2 py-1 border rounded" onClick={() => shift(1)}>&gt;</button>
            </div>

            {cards.length === 0 ? (
                <div className="rounded-lg border border-slate-200 bg-white px-4 py-10 text-center text-slate-500">
                    No hay movimientos de tarjetas en este período.
                </div>
            ) : (
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    {cards.map((tarjeta) => (
                        <TarjetaCard
                            key={tarjeta.id_cuenta}
                            tarjeta={tarjeta}
                            cuentasOrigen={cuentasOrigen}
                            year={year}
                            month={month}
                            onTotalsChange={onTotalsChange}
                        />
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
