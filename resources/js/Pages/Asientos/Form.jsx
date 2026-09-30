import AdminLayout from '@/Layouts/AdminLayout';
import CuentaSelect from '@/Components/CuentaSelect';
import DecimalInput from '@/Components/DecimalInput';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { formatDecimalInput, parseDecimalInput } from '@/utils/decimalInput';
import { toDateInputValue } from '@/utils/dateFormat';
import { indexHrefFromListState } from '@/utils/listState';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

function emptyItem() {
    return {
        id_cuenta: '',
        id_moneda: '',
        unidades: '',
        debe_origen: '',
        haber_origen: '',
        cotizacion: formatDecimalInput('1', 10),
    };
}

function mapItems(items) {
    if (!items?.length) {
        return [emptyItem(), emptyItem()];
    }

    return items.map((item) => ({
        id_cuenta: item.id_cuenta || '',
        id_moneda: item.id_moneda || '',
        unidades: item.unidades ?? '',
        debe_origen: formatDecimalInput(item.debe_origen, 2),
        haber_origen: formatDecimalInput(item.haber_origen, 2),
        cotizacion: formatDecimalInput(item.cotizacion ?? '1', 10),
    }));
}

async function fetchCotizacion({ idMoneda, fecha, idCuenta, promedio }) {
    if (!idMoneda || !fecha) return null;
    const params = new URLSearchParams({ id_moneda: idMoneda, fecha });
    if (promedio && idCuenta) {
        params.set('promedio', '1');
        params.set('id_cuenta', idCuenta);
    }
    const res = await fetch(`${route('asientos.cotizacion')}?${params}`);
    const json = await res.json();
    return json.cotizacion;
}

function usePromedioForItem(item) {
    const haber = parseDecimalInput(item.haber_origen) || '0';
    return Number(haber) !== 0;
}

function lineLocal(item) {
    const cot = parseDecimalInput(item.cotizacion) || '1';
    const debe = parseDecimalInput(item.debe_origen) || '0';
    const haber = parseDecimalInput(item.haber_origen) || '0';
    return {
        debe: Number(debe) * Number(cot),
        haber: Number(haber) * Number(cot),
    };
}

export default function Form({ asiento, monedas, cuentas, isCopy = false, isPrefill = false }) {
    const isEdit = Boolean(asiento?.id);

    const form = useForm({
        fecha: asiento?.fecha?.substring?.(0, 10) || asiento?.fecha || toDateInputValue(),
        descripcion: asiento?.descripcion || '',
        items: mapItems(asiento?.items),
    });

    const [fetchingCotizacionLine, setFetchingCotizacionLine] = useState(null);

    const totals = useMemo(() => {
        return form.data.items.reduce(
            (acc, item) => {
                const local = lineLocal(item);
                acc.debe += local.debe;
                acc.haber += local.haber;
                return acc;
            },
            { debe: 0, haber: 0 },
        );
    }, [form.data.items]);

    const diferencia = totals.debe - totals.haber;

    useEffect(() => {
        if (isEdit || isPrefill) return undefined;

        let active = true;

        (async () => {
            const nextItems = [...form.data.items];
            let changed = false;

            for (let i = 0; i < nextItems.length; i++) {
                const item = nextItems[i];
                if (!item.id_moneda || !form.data.fecha) continue;

                const rate = await fetchCotizacion({
                    idMoneda: item.id_moneda,
                    fecha: form.data.fecha,
                    idCuenta: item.id_cuenta,
                    promedio: usePromedioForItem(item),
                });
                if (!active || rate == null) continue;

                const formatted = formatDecimalInput(rate, 10);
                if (item.cotizacion !== formatted) {
                    nextItems[i] = { ...item, cotizacion: formatted };
                    changed = true;
                }
            }

            if (active && changed) {
                form.setData('items', nextItems);
            }
        })();

        return () => { active = false; };
    }, [
        isEdit,
        isPrefill,
        form.data.fecha,
        form.data.items.map((i) => [
            i.id_moneda,
            i.id_cuenta,
            i.debe_origen,
            i.haber_origen,
        ].join('|')).join(';'),
    ]);

    const updateItem = (index, patch) => {
        const items = form.data.items.map((item, i) => (i === index ? { ...item, ...patch } : item));
        form.setData('items', items);
    };

    const onCuentaChange = (index, idCuenta) => {
        const cuenta = cuentas.find((c) => String(c.id) === String(idCuenta));
        updateItem(index, {
            id_cuenta: idCuenta,
            ...(cuenta?.id_moneda ? { id_moneda: cuenta.id_moneda } : {}),
        });
    };

    const addItem = () => form.setData('items', [...form.data.items, emptyItem()]);
    const removeItem = (index) => {
        if (form.data.items.length <= 2) return;
        form.setData('items', form.data.items.filter((_, i) => i !== index));
    };

    const fetchCotizacionDelDia = async (index) => {
        const item = form.data.items[index];
        if (!item.id_moneda || !form.data.fecha) return;

        setFetchingCotizacionLine(index);
        try {
            const rate = await fetchCotizacion({
                idMoneda: item.id_moneda,
                fecha: form.data.fecha,
                promedio: false,
            });
            if (rate != null) {
                updateItem(index, { cotizacion: formatDecimalInput(rate, 10) });
            }
        } finally {
            setFetchingCotizacionLine(null);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            form.put(route('asientos.update', asiento.id));
        } else {
            form.post(route('asientos.store'));
        }
    };

    const formatMoney = (n) => n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const indexHref = indexHrefFromListState('asientos.index');

    return (
        <AdminLayout header={isEdit ? 'Editar asiento' : isCopy ? 'Nuevo asiento (copia)' : isPrefill ? 'Nuevo asiento (borrador)' : 'Nuevo asiento'}>
            <Head title={isEdit ? 'Editar asiento' : 'Nuevo asiento'} />

            <form onSubmit={submit} className="space-y-4 max-w-6xl">
                <div className="bg-white rounded-lg shadow-sm border border-slate-200 p-6 space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <InputLabel value="Fecha" />
                            <TextInput type="date" className="mt-1 block w-full" value={form.data.fecha} onChange={(e) => form.setData('fecha', e.target.value)} />
                            <InputError message={form.errors.fecha} />
                        </div>
                        <div className="md:col-span-2">
                            <InputLabel value="Descripción" />
                            <TextInput className="mt-1 block w-full" value={form.data.descripcion} onChange={(e) => form.setData('descripcion', e.target.value)} />
                            <InputError message={form.errors.descripcion} />
                        </div>
                    </div>
                </div>

                <div className="bg-white rounded-lg shadow-sm border border-slate-200 overflow-x-auto">
                    <table className="min-w-[980px] w-full text-sm">
                        <thead className="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th className="px-2 py-2 text-left">Cuenta</th>
                                <th className="px-2 py-2 text-left w-20">Un.</th>
                                <th className="px-2 py-2 text-left w-28">Debe</th>
                                <th className="px-2 py-2 text-left w-28">Haber</th>
                                <th className="px-2 py-2 text-left w-28">Moneda</th>
                                <th className="px-2 py-2 text-left min-w-[13rem]">Cotización</th>
                                <th className="px-2 py-2 w-10" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {form.data.items.map((item, index) => {
                                const cuenta = cuentas.find((c) => String(c.id) === String(item.id_cuenta));
                                const monedaLocked = Boolean(cuenta?.id_moneda);
                                const moneda = monedas.find((m) => String(m.id) === String(item.id_moneda));
                                const showCotizacionDia = moneda && !moneda.local;

                                return (
                                    <tr key={index}>
                                        <td className="px-2 py-2 min-w-[16rem]">
                                            <CuentaSelect
                                                cuentas={cuentas}
                                                value={item.id_cuenta}
                                                onChange={(id) => onCuentaChange(index, id)}
                                                inputClassName="w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm pr-8"
                                            />
                                            <InputError message={form.errors[`items.${index}.id_cuenta`]} />
                                        </td>
                                        <td className="px-2 py-2">
                                            <TextInput
                                                type="number"
                                                className="block w-full text-sm"
                                                value={item.unidades}
                                                onChange={(e) => updateItem(index, { unidades: e.target.value })}
                                            />
                                        </td>
                                        <td className="px-2 py-2">
                                            <DecimalInput
                                                className="block w-full text-sm"
                                                decimals={2}
                                                value={item.debe_origen}
                                                onChange={(value) => updateItem(index, { debe_origen: value, haber_origen: value ? formatDecimalInput('0', 2) : item.haber_origen })}
                                            />
                                        </td>
                                        <td className="px-2 py-2">
                                            <DecimalInput
                                                className="block w-full text-sm"
                                                decimals={2}
                                                value={item.haber_origen}
                                                onChange={(value) => updateItem(index, { haber_origen: value, debe_origen: value ? formatDecimalInput('0', 2) : item.debe_origen })}
                                            />
                                        </td>
                                        <td className="px-2 py-2">
                                            <select
                                                className="block w-full rounded-md border-slate-300 text-sm"
                                                value={item.id_moneda}
                                                disabled={monedaLocked}
                                                onChange={(e) => updateItem(index, { id_moneda: e.target.value })}
                                            >
                                                <option value="">—</option>
                                                {monedas.map((m) => (
                                                    <option key={m.id} value={m.id}>{m.simbolo}</option>
                                                ))}
                                            </select>
                                        </td>
                                        <td className="px-2 py-2">
                                            <div className="flex items-center gap-1">
                                                <DecimalInput
                                                    className="block w-full min-w-[11rem] text-sm font-mono"
                                                    decimals={10}
                                                    value={item.cotizacion}
                                                    onChange={(value) => updateItem(index, { cotizacion: value })}
                                                />
                                                {showCotizacionDia && (
                                                    <button
                                                        type="button"
                                                        title="Cotización del día"
                                                        disabled={fetchingCotizacionLine === index || !form.data.fecha}
                                                        onClick={() => fetchCotizacionDelDia(index)}
                                                        className="shrink-0 rounded border border-slate-300 px-1.5 py-1 text-xs text-slate-600 hover:bg-slate-50 disabled:opacity-40"
                                                    >
                                                        {fetchingCotizacionLine === index ? '…' : 'Día'}
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-2 py-2 text-center">
                                            <button
                                                type="button"
                                                className="text-red-500 hover:text-red-700 disabled:opacity-30"
                                                disabled={form.data.items.length <= 2}
                                                onClick={() => removeItem(index)}
                                            >
                                                ×
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                        <tfoot className="bg-slate-50 border-t border-slate-200">
                            <tr>
                                <td className="px-2 py-3" colSpan={2}>
                                    <button type="button" onClick={addItem} className="text-sm text-emerald-700 hover:underline">
                                        + Agregar línea
                                    </button>
                                </td>
                                <td className="px-2 py-3 font-mono text-right font-medium">{formatMoney(totals.debe)}</td>
                                <td className="px-2 py-3 font-mono text-right font-medium">{formatMoney(totals.haber)}</td>
                                <td className="px-2 py-3" colSpan={3}>
                                    <span className={`text-sm font-medium ${Math.abs(diferencia) > 0.009 ? 'text-red-600' : 'text-emerald-700'}`}>
                                        Diferencia: {formatMoney(diferencia)}
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                    <InputError message={form.errors.items} className="px-4 pb-3" />
                </div>

                <div className="flex gap-3">
                    <PrimaryButton disabled={form.processing || Math.abs(diferencia) > 0.009}>Guardar</PrimaryButton>
                    <Link href={indexHref} className="text-sm text-slate-600 underline self-center">Cancelar</Link>
                </div>
            </form>
        </AdminLayout>
    );
}
