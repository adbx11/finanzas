import AdminLayout from '@/Layouts/AdminLayout';
import CuentaSelect from '@/Components/CuentaSelect';
import DecimalInput from '@/Components/DecimalInput';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { formatDecimalInput } from '@/utils/decimalInput';
import { toDateInputValue } from '@/utils/dateFormat';
import { indexHrefFromListState } from '@/utils/listState';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect } from 'react';

async function fetchCotizacion(idMoneda, fecha) {
    if (!idMoneda || !fecha) return null;
    const params = new URLSearchParams({ id_moneda: idMoneda, fecha });
    const res = await fetch(`${route('pagos.cotizacion')}?${params}`);
    const json = await res.json();
    return json.cotizacion;
}

export default function Form({ pago, monedas, cuentasConcepto, cuentasOrigen, isCuota = false, isCopy = false }) {
    const isEdit = Boolean(pago?.id);

    const form = useForm({
        fecha: pago?.fecha?.substring(0, 10) || toDateInputValue(),
        id_cuenta_concepto: pago?.id_cuenta_concepto || '',
        id_cuenta_origen: pago?.id_cuenta_origen || '',
        id_moneda: pago?.id_moneda || monedas.find((m) => m.local)?.id || '',
        importe: formatDecimalInput(pago?.importe, 2),
        cotizacion: formatDecimalInput(pago?.cotizacion ?? '1', 10),
        comentarios: pago?.comentarios || '',
        cuotas: pago?.cuotas ?? 0,
    });

    useEffect(() => {
        let active = true;
        (async () => {
            const rate = await fetchCotizacion(form.data.id_moneda, form.data.fecha);
            if (active && rate != null) {
                form.setData('cotizacion', formatDecimalInput(rate, 10));
            }
        })();
        return () => { active = false; };
    }, [form.data.id_moneda, form.data.fecha]);

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            form.put(route('pagos.update', pago.id));
        } else {
            form.post(route('pagos.store'));
        }
    };

    return (
        <AdminLayout header={isEdit ? 'Editar pago' : isCopy ? 'Nuevo pago (copia)' : 'Nuevo pago'}>
            <Head title={isEdit ? 'Editar pago' : isCopy ? 'Nuevo pago (copia)' : 'Nuevo pago'} />

            {isCuota && (
                <p className="mb-4 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-3 py-2 max-w-3xl">
                    Este registro es una cuota generada. Al guardar solo se actualiza esta cuota, no se regeneran las demás.
                </p>
            )}

            <form onSubmit={submit} className="bg-white rounded-lg shadow-sm border border-slate-200 p-6 max-w-3xl space-y-4">
                <div>
                    <InputLabel value="Fecha" />
                    <TextInput type="date" className="mt-1 block w-full" value={form.data.fecha} onChange={(e) => form.setData('fecha', e.target.value)} />
                    <InputError message={form.errors.fecha} />
                </div>

                <div>
                    <InputLabel value="Concepto" />
                    <div className="mt-1">
                        <CuentaSelect
                            cuentas={cuentasConcepto}
                            value={form.data.id_cuenta_concepto}
                            onChange={(id) => form.setData('id_cuenta_concepto', id)}
                        />
                    </div>
                    <InputError message={form.errors.id_cuenta_concepto} />
                </div>

                <div>
                    <InputLabel value="Origen" />
                    <div className="mt-1">
                        <CuentaSelect
                            cuentas={cuentasOrigen}
                            value={form.data.id_cuenta_origen}
                            onChange={(id, cuenta) => {
                                form.setData((data) => ({
                                    ...data,
                                    id_cuenta_origen: id,
                                    ...(cuenta?.id_moneda ? { id_moneda: cuenta.id_moneda } : {}),
                                }));
                            }}
                        />
                    </div>
                    <InputError message={form.errors.id_cuenta_origen} />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <InputLabel value="Moneda" />
                        <select className="mt-1 block w-full rounded-md border-slate-300" value={form.data.id_moneda} onChange={(e) => form.setData('id_moneda', e.target.value)}>
                            {monedas.map((m) => (
                                <option key={m.id} value={m.id}>{m.simbolo} ({m.codigo})</option>
                            ))}
                        </select>
                        <InputError message={form.errors.id_moneda} />
                    </div>
                    <div>
                        <InputLabel value="Importe" />
                        <DecimalInput
                            className="mt-1 block w-full"
                            decimals={2}
                            value={form.data.importe}
                            onChange={(value) => form.setData('importe', value)}
                        />
                        <InputError message={form.errors.importe} />
                    </div>
                    <div>
                        <InputLabel value="Cotización" />
                        <DecimalInput
                            className="mt-1 block w-full"
                            decimals={10}
                            value={form.data.cotizacion}
                            onChange={(value) => form.setData('cotizacion', value)}
                        />
                        <InputError message={form.errors.cotizacion} />
                    </div>
                </div>

                {!isCuota && (
                    <div>
                        <InputLabel value="Cuotas (tarjeta)" />
                        <TextInput type="number" min="0" max="48" className="mt-1 block w-full max-w-xs" value={form.data.cuotas} onChange={(e) => form.setData('cuotas', e.target.value)} />
                        <p className="mt-1 text-xs text-slate-500">0 = sin cuotas. Si indicás cuotas, se generan pagos futuros el día 15 de cada mes.</p>
                        <InputError message={form.errors.cuotas} />
                    </div>
                )}

                <div>
                    <InputLabel value="Comentarios" />
                    <TextInput className="mt-1 block w-full" value={form.data.comentarios} onChange={(e) => form.setData('comentarios', e.target.value)} />
                    <InputError message={form.errors.comentarios} />
                </div>

                <div className="flex gap-3">
                    <PrimaryButton disabled={form.processing}>Guardar</PrimaryButton>
                    <Link href={indexHrefFromListState('pagos.index')} className="text-sm text-slate-600 underline self-center">Cancelar</Link>
                </div>
            </form>
        </AdminLayout>
    );
}
