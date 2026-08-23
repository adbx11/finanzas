import AdminLayout from '@/Layouts/AdminLayout';
import DateFilterInput from '@/Components/DateFilterInput';
import DecimalInput from '@/Components/DecimalInput';
import PrimaryButton from '@/Components/PrimaryButton';
import { formatDecimalInput, parseDecimalInput } from '@/utils/decimalInput';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

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

function buildRows(cuentas) {
    return (cuentas || []).map((c) => ({
        id_cuenta: c.id,
        codigo: c.codigo,
        descripcion: c.descripcion,
        moneda: c.moneda,
        saldo: c.saldo,
        saldo_real: formatDecimalInput(c.saldo, 2),
    }));
}

export default function Index({ fecha, cuentas }) {
    const { flash } = usePage().props;
    const [rows, setRows] = useState(() => buildRows(cuentas));

    useEffect(() => {
        setRows(buildRows(cuentas));
    }, [cuentas]);

    const form = useForm({
        fecha,
        lineas: [],
    });

    const changeFecha = (next) => {
        router.get(route('conciliacion.index'), { fecha: next }, { preserveState: false, replace: true });
    };

    const updateSaldoReal = (index, value) => {
        setRows((prev) => prev.map((row, i) => (i === index ? { ...row, saldo_real: value } : row)));
    };

    const diffs = useMemo(
        () => rows.map((row) => {
            const diff = toNumber(row.saldo_real) - toNumber(row.saldo);
            return Number(diff.toFixed(2));
        }),
        [rows],
    );

    const hasDiff = diffs.some((d) => d !== 0);

    const submit = (e) => {
        e.preventDefault();
        form
            .transform(() => ({
                fecha,
                lineas: rows.map((row) => ({
                    id_cuenta: row.id_cuenta,
                    saldo_real: row.saldo_real,
                })),
            }))
            .post(route('conciliacion.store'), { preserveScroll: true });
    };

    return (
        <AdminLayout header="Conciliación">
            <Head title="Conciliación" />

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

            <form onSubmit={submit} className="space-y-4 max-w-4xl">
                <div className="flex flex-wrap items-end gap-3">
                    <div>
                        <label className="block text-xs text-slate-500 mb-1">Fecha</label>
                        <DateFilterInput value={fecha} onCommit={changeFecha} />
                    </div>
                    <p className="text-sm text-slate-500 pb-1">
                        Cuentas de activo líquido (1.1). La diferencia se ajusta contra 5.9.00.00.
                    </p>
                </div>

                <div className="bg-white rounded-md border border-slate-200 overflow-x-auto">
                    <table className="min-w-[520px] w-full text-sm">
                        <thead className="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th className="px-2 py-1 text-left font-medium text-slate-600">Cuenta</th>
                                <th className="px-2 py-1 text-right font-medium text-slate-600 w-24">Saldo</th>
                                <th className="px-2 py-1 text-right font-medium text-slate-600 w-28">Saldo real</th>
                                <th className="px-2 py-1 text-right font-medium text-slate-600 w-20">Dif.</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {rows.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="px-2 py-4 text-center text-slate-500">
                                        No hay cuentas 1.1 imputables habilitadas.
                                    </td>
                                </tr>
                            ) : (
                                rows.map((row, index) => {
                                    const diff = diffs[index] ?? 0;
                                    return (
                                        <tr key={row.id_cuenta} className={index % 2 === 1 ? 'bg-slate-50/80' : 'bg-white'}>
                                            <td className="px-2 py-0.5 whitespace-nowrap">
                                                <span className="font-mono text-xs text-slate-500 mr-1.5">{row.codigo}</span>
                                                <span className="text-slate-800">{row.descripcion}</span>
                                                {row.moneda?.simbolo && (
                                                    <span className="ml-1 text-xs text-slate-400">{row.moneda.simbolo}</span>
                                                )}
                                            </td>
                                            <td className="px-2 py-0.5 text-right font-mono whitespace-nowrap tabular-nums">
                                                {formatMoney(row.saldo)}
                                            </td>
                                            <td className="px-2 py-0.5">
                                                <DecimalInput
                                                    className="block w-full text-sm text-right py-0.5 px-1.5"
                                                    decimals={2}
                                                    value={row.saldo_real}
                                                    onChange={(value) => updateSaldoReal(index, value)}
                                                />
                                            </td>
                                            <td className={`px-2 py-0.5 text-right font-mono whitespace-nowrap tabular-nums ${
                                                diff === 0 ? 'text-slate-400' : diff > 0 ? 'text-emerald-700' : 'text-red-600'
                                            }`}
                                            >
                                                {formatMoney(diff)}
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center gap-3">
                    <PrimaryButton disabled={form.processing || rows.length === 0}>
                        Guardar
                    </PrimaryButton>
                    {!hasDiff && rows.length > 0 && (
                        <span className="text-sm text-slate-500">Sin diferencias respecto al saldo del sistema.</span>
                    )}
                </div>
            </form>
        </AdminLayout>
    );
}
