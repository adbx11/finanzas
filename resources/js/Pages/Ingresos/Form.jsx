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
    const res = await fetch(`${route('ingresos.cotizacion')}?${params}`);
    const json = await res.json();
    return json.cotizacion;
}

export default function Form({ ingreso, monedas, cuentasConcepto, cuentasDestino, isCopy = false }) {
    const isEdit = Boolean(ingreso?.id);

    const form = useForm({
        fecha: ingreso?.fecha?.substring(0, 10) || toDateInputValue(),
        id_cuenta_concepto: ingreso?.id_cuenta_concepto || '',
        id_cuenta_destino: ingreso?.id_cuenta_destino || '',
        id_moneda: ingreso?.id_moneda || monedas.find((m) => m.local)?.id || '',
        importe: formatDecimalInput(ingreso?.importe, 2),
        cotizacion: formatDecimalInput(ingreso?.cotizacion ?? '1', 10),
        comentarios: ingreso?.comentarios || '',
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
            form.put(route('ingresos.update', ingreso.id));
        } else {
            form.post(route('ingresos.store'));
        }
    };

    return (
        <AdminLayout header={isEdit ? 'Editar ingreso' : isCopy ? 'Nuevo ingreso (copia)' : 'Nuevo ingreso'}>
            <Head title={isEdit ? 'Editar ingreso' : isCopy ? 'Nuevo ingreso (copia)' : 'Nuevo ingreso'} />

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
                    <InputLabel value="Destino" />
                    <div className="mt-1">
                        <CuentaSelect
                            cuentas={cuentasDestino}
                            value={form.data.id_cuenta_destino}
                            onChange={(id, cuenta) => {
                                form.setData((data) => ({
                                    ...data,
                                    id_cuenta_destino: id,
                                    ...(cuenta?.id_moneda ? { id_moneda: cuenta.id_moneda } : {}),
                                }));
                            }}
                        />
                    </div>
                    <InputError message={form.errors.id_cuenta_destino} />
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
                            className="mt-1 block w-full min-w-[11rem] font-mono"
                            decimals={10}
                            value={form.data.cotizacion}
                            onChange={(value) => form.setData('cotizacion', value)}
                        />
                        <InputError message={form.errors.cotizacion} />
                    </div>
                </div>

                <div>
                    <InputLabel value="Comentarios" />
                    <TextInput className="mt-1 block w-full" value={form.data.comentarios} onChange={(e) => form.setData('comentarios', e.target.value)} />
                    <InputError message={form.errors.comentarios} />
                </div>

                <div className="flex gap-3">
                    <PrimaryButton disabled={form.processing}>Guardar</PrimaryButton>
                    <Link href={indexHrefFromListState('ingresos.index')} className="text-sm text-slate-600 underline self-center">Cancelar</Link>
                </div>
            </form>
        </AdminLayout>
    );
}
