import AdminLayout from '@/Layouts/AdminLayout';
import FilterableTable from '@/Components/DataTable/FilterableTable';
import RowActions from '@/Components/DataTable/RowActions';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { useDataTableQuery } from '@/hooks/useDataTableQuery';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

function CuentaForm({ cuenta, monedas, cuentasSuperiores, onCancel }) {
    const form = useForm({
        codigo: cuenta?.codigo || '',
        descripcion: cuenta?.descripcion || '',
        id_superior: cuenta?.id_superior || '',
        id_moneda: cuenta?.id_moneda || '',
        tipo_estado: cuenta?.tipo_estado || 'A',
        tipo_cuenta: cuenta?.tipo_cuenta || '',
        clase: cuenta?.clase || '',
        imputable: cuenta?.imputable ?? false,
        habilitada: cuenta?.habilitada ?? true,
    });

    const submit = (e) => {
        e.preventDefault();
        const payload = {
            ...form.data,
            id_superior: form.data.id_superior || null,
            id_moneda: form.data.id_moneda || null,
        };
        if (cuenta) {
            router.put(route('cuentas.update', cuenta.id), payload, { onSuccess: onCancel });
        } else {
            router.post(route('cuentas.store'), payload, { onSuccess: onCancel });
        }
    };

    return (
        <form onSubmit={submit} className="bg-slate-50 border border-slate-200 rounded-lg p-4 mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <InputLabel value="Código" />
                <TextInput className="mt-1 block w-full" value={form.data.codigo} onChange={(e) => form.setData('codigo', e.target.value)} />
                <InputError message={form.errors.codigo} />
            </div>
            <div className="md:col-span-2">
                <InputLabel value="Descripción" />
                <TextInput className="mt-1 block w-full" value={form.data.descripcion} onChange={(e) => form.setData('descripcion', e.target.value)} />
                <InputError message={form.errors.descripcion} />
            </div>
            <div>
                <InputLabel value="Superior" />
                <select className="mt-1 block w-full rounded-md border-slate-300" value={form.data.id_superior} onChange={(e) => form.setData('id_superior', e.target.value)}>
                    <option value="">—</option>
                    {cuentasSuperiores.map((c) => (
                        <option key={c.id} value={c.id}>{c.codigo} {c.descripcion}</option>
                    ))}
                </select>
            </div>
            <div>
                <InputLabel value="Moneda" />
                <select className="mt-1 block w-full rounded-md border-slate-300" value={form.data.id_moneda} onChange={(e) => form.setData('id_moneda', e.target.value)}>
                    <option value="">—</option>
                    {monedas.map((m) => (
                        <option key={m.id} value={m.id}>{m.codigo} ({m.simbolo})</option>
                    ))}
                </select>
            </div>
            <div>
                <InputLabel value="Clase" />
                <TextInput className="mt-1 block w-full" value={form.data.clase} onChange={(e) => form.setData('clase', e.target.value)} />
            </div>
            <div>
                <InputLabel value="Tipo estado" />
                <TextInput className="mt-1 block w-full" value={form.data.tipo_estado} onChange={(e) => form.setData('tipo_estado', e.target.value)} />
            </div>
            <div>
                <InputLabel value="Tipo cuenta" />
                <TextInput className="mt-1 block w-full" value={form.data.tipo_cuenta} onChange={(e) => form.setData('tipo_cuenta', e.target.value)} />
            </div>
            <div className="flex items-center gap-6">
                <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.imputable} onChange={(e) => form.setData('imputable', e.target.checked)} /> Imputable</label>
                <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.habilitada} onChange={(e) => form.setData('habilitada', e.target.checked)} /> Habilitada</label>
            </div>
            <div className="md:col-span-3 flex gap-2">
                <PrimaryButton disabled={form.processing}>Guardar</PrimaryButton>
                {onCancel && <button type="button" onClick={onCancel} className="text-sm text-slate-600 underline">Cancelar</button>}
            </div>
        </form>
    );
}

export default function Index({ cuentas, monedas, cuentasSuperiores, filters: initialFilters, sort: initialSort, direction: initialDirection }) {
    const [editing, setEditing] = useState(null);
    const [creating, setCreating] = useState(false);
    const [copying, setCopying] = useState(null);
    const {
        filters,
        sort,
        direction,
        setFilter,
        clearFilters,
        toggleSort,
        goToPage,
        hasActiveFilters,
    } = useDataTableQuery('cuentas.index', { filters: initialFilters, sort: initialSort, direction: initialDirection });

    const closeForm = () => {
        setCreating(false);
        setEditing(null);
        setCopying(null);
    };

    const columns = [
        {
            key: 'codigo',
            header: 'Código',
            filterKey: 'codigo',
            sortKey: 'codigo',
            className: 'font-mono',
            render: (row) => row.codigo,
        },
        {
            key: 'descripcion',
            header: 'Descripción',
            filterKey: 'descripcion',
            sortKey: 'descripcion',
            render: (row) => row.descripcion,
        },
        {
            key: 'moneda',
            header: 'Moneda',
            filterKey: 'moneda',
            sortKey: 'moneda',
            render: (row) => row.moneda?.simbolo || '—',
        },
        {
            key: 'clase',
            header: 'Clase',
            filterKey: 'clase',
            sortKey: 'clase',
            render: (row) => row.clase,
        },
        {
            key: 'imputable',
            header: 'Imp.',
            filterKey: 'imputable',
            sortKey: 'imputable',
            filterPlaceholder: 'sí/no',
            render: (row) => (row.imputable ? 'Sí' : ''),
        },
        {
            key: 'actions',
            header: '',
            filterable: false,
            sortable: false,
            align: 'right',
            render: (row) => (
                <RowActions
                    onEdit={() => { setEditing(row); setCreating(false); setCopying(null); }}
                    onCopy={() => { setCopying({ ...row, id: undefined }); setCreating(true); setEditing(null); }}
                    destroyRoute="cuentas.destroy"
                    destroyId={row.id}
                    destroyMessage="¿Eliminar?"
                />
            ),
        },
    ];

    return (
        <AdminLayout header="Cuentas">
            <Head title="Cuentas" />

            <div className="flex flex-wrap gap-3 mb-4 items-center justify-between">
                <PrimaryButton onClick={() => { setCreating(true); setEditing(null); setCopying(null); }}>Agregar</PrimaryButton>
            </div>

            {(creating || editing || copying) && (
                <CuentaForm cuenta={editing || copying} monedas={monedas} cuentasSuperiores={cuentasSuperiores} onCancel={closeForm} />
            )}

            <FilterableTable
                columns={columns}
                rows={cuentas.data}
                filters={filters}
                onFilterChange={setFilter}
                onClearFilters={clearFilters}
                hasActiveFilters={hasActiveFilters}
                sort={sort}
                direction={direction}
                onSortChange={toggleSort}
                pagination={cuentas}
                onPageChange={goToPage}
            />
        </AdminLayout>
    );
}
