import AdminLayout from '@/Layouts/AdminLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

function ConfigForm({ item, onCancel }) {
    const isEdit = Boolean(item?.id);
    const form = useForm({
        clave: item?.clave || '',
        valor: item?.valor || '',
        tipo: item?.tipo || 'string',
        nombre: item?.nombre || '',
        grupo: item?.grupo || 'General',
        orden: item?.orden ?? 100,
        grupo_orden: item?.grupo_orden ?? 99,
    });

    const submit = (e) => {
        e.preventDefault();
        const opts = { onSuccess: onCancel, preserveScroll: true };
        if (isEdit) {
            form.put(route('configuracion.update', item.id), opts);
        } else {
            form.post(route('configuracion.store'), opts);
        }
    };

    return (
        <form onSubmit={submit} className="bg-slate-50 border border-slate-200 rounded-lg p-4 mb-4 grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
            {!isEdit && (
                <div>
                    <InputLabel value="Clave" />
                    <TextInput className="mt-1 block w-full font-mono text-sm" value={form.data.clave} onChange={(e) => form.setData('clave', e.target.value)} />
                    <InputError message={form.errors.clave} />
                </div>
            )}
            <div>
                <InputLabel value="Nombre" />
                <TextInput className="mt-1 block w-full" value={form.data.nombre} onChange={(e) => form.setData('nombre', e.target.value)} />
                <InputError message={form.errors.nombre} />
            </div>
            <div className={isEdit ? 'md:col-span-2' : ''}>
                <InputLabel value="Valor" />
                <TextInput className="mt-1 block w-full font-mono text-sm" value={form.data.valor} onChange={(e) => form.setData('valor', e.target.value)} />
                <InputError message={form.errors.valor} />
            </div>
            <div>
                <InputLabel value="Grupo" />
                <TextInput className="mt-1 block w-full" value={form.data.grupo} onChange={(e) => form.setData('grupo', e.target.value)} />
            </div>
            <div>
                <InputLabel value="Tipo" />
                <TextInput className="mt-1 block w-full" value={form.data.tipo} onChange={(e) => form.setData('tipo', e.target.value)} />
            </div>
            <div className="flex gap-2">
                <PrimaryButton disabled={form.processing}>Guardar</PrimaryButton>
                <button type="button" onClick={onCancel} className="text-sm text-slate-600 underline">Cancelar</button>
            </div>
        </form>
    );
}

export default function Index({ groups }) {
    const { flash } = usePage().props;
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState(null);

    const destroy = (item) => {
        if (confirm(`¿Eliminar ${item.clave}?`)) {
            router.delete(route('configuracion.destroy', item.id));
        }
    };

    return (
        <AdminLayout header="Configuración">
            <Head title="Configuración" />

            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{flash.success}</div>
            )}

            <div className="mb-4">
                <PrimaryButton onClick={() => { setCreating(true); setEditing(null); }}>Agregar</PrimaryButton>
            </div>

            {(creating || editing) && (
                <ConfigForm
                    item={editing}
                    onCancel={() => { setCreating(false); setEditing(null); }}
                />
            )}

            <div className="space-y-6">
                {(groups || []).map((group) => (
                    <div key={group.grupo} className="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
                        <div className="px-4 py-2 border-b border-slate-100 bg-slate-50">
                            <h3 className="text-sm font-medium text-slate-700">{group.grupo}</h3>
                        </div>
                        <div className="divide-y divide-slate-100">
                            {(group.items || []).map((item) => (
                                <div key={item.id} className="px-4 py-3 flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <div className="text-sm font-medium text-slate-800">{item.nombre || item.clave}</div>
                                        <div className="text-xs font-mono text-slate-400">{item.clave}</div>
                                        <div className="mt-1 text-sm font-mono text-slate-700 break-all">{item.valor || '—'}</div>
                                    </div>
                                    <div className="flex gap-3 text-sm shrink-0">
                                        <button
                                            type="button"
                                            className="text-emerald-700 underline"
                                            onClick={() => { setEditing(item); setCreating(false); }}
                                        >
                                            Editar
                                        </button>
                                        <button type="button" className="text-red-600 underline" onClick={() => destroy(item)}>
                                            Eliminar
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </AdminLayout>
    );
}
