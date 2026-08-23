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

function MonedaForm({ moneda, onCancel }) {
    const form = useForm({
        codigo: moneda?.codigo || '',
        simbolo: moneda?.simbolo || '',
        local: moneda?.local || false,
    });

    const submit = (e) => {
        e.preventDefault();
        if (moneda) {
            form.put(route('monedas.update', moneda.id), { onSuccess: onCancel });
        } else {
            form.post(route('monedas.store'), { onSuccess: onCancel });
        }
    };

    return (
        <form onSubmit={submit} className="bg-slate-50 border border-slate-200 rounded-lg p-4 mb-6 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <InputLabel value="Código" />
                <TextInput className="mt-1 block w-full" value={form.data.codigo} onChange={(e) => form.setData('codigo', e.target.value)} />
                <InputError message={form.errors.codigo} />
            </div>
            <div>
                <InputLabel value="Símbolo" />
                <TextInput className="mt-1 block w-full" value={form.data.simbolo} onChange={(e) => form.setData('simbolo', e.target.value)} />
                <InputError message={form.errors.simbolo} />
            </div>
            <div>
                <label className="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" checked={form.data.local} onChange={(e) => form.setData('local', e.target.checked)} />
                    Moneda local
                </label>
            </div>
            <div className="flex gap-2">
                <PrimaryButton disabled={form.processing}>Guardar</PrimaryButton>
                {onCancel && (
                    <button type="button" onClick={onCancel} className="text-sm text-slate-600 underline">
                        Cancelar
                    </button>
                )}
            </div>
        </form>
    );
}

export default function Index({ monedas, filters: initialFilters, sort: initialSort, direction: initialDirection }) {
    const [editing, setEditing] = useState(null);
    const [creating, setCreating] = useState(false);
    const {
        filters,
        sort,
        direction,
        setFilter,
        clearFilters,
        toggleSort,
        hasActiveFilters,
    } = useDataTableQuery('monedas.index', { filters: initialFilters, sort: initialSort, direction: initialDirection });

    const destroy = (id) => {
        if (confirm('¿Eliminar moneda?')) {
            router.delete(route('monedas.destroy', id));
        }
    };

    const columns = [
        {
            key: 'codigo',
            header: 'Código',
            filterKey: 'codigo',
            sortKey: 'codigo',
            render: (row) => row.codigo,
        },
        {
            key: 'simbolo',
            header: 'Símbolo',
            filterKey: 'simbolo',
            sortKey: 'simbolo',
            render: (row) => row.simbolo,
        },
        {
            key: 'local',
            header: 'Local',
            filterKey: 'local',
            sortKey: 'local',
            filterPlaceholder: 'sí/no',
            render: (row) => (row.local ? 'Sí' : ''),
        },
        {
            key: 'actions',
            header: '',
            filterable: false,
            sortable: false,
            align: 'right',
            render: (row) => (
                <RowActions
                    onEdit={() => { setEditing(row); setCreating(false); }}
                    onDelete={() => destroy(row.id)}
                />
            ),
        },
    ];

    return (
        <AdminLayout header="Monedas">
            <Head title="Monedas" />

            <div className="mb-4">
                <PrimaryButton onClick={() => { setCreating(true); setEditing(null); }}>Agregar</PrimaryButton>
            </div>

            {(creating || editing) && (
                <MonedaForm moneda={editing} onCancel={() => { setCreating(false); setEditing(null); }} />
            )}

            <FilterableTable
                columns={columns}
                rows={monedas}
                filters={filters}
                onFilterChange={setFilter}
                onClearFilters={clearFilters}
                hasActiveFilters={hasActiveFilters}
                sort={sort}
                direction={direction}
                onSortChange={toggleSort}
            />
        </AdminLayout>
    );
}
