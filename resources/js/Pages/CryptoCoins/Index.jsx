import AdminLayout from '@/Layouts/AdminLayout';
import FilterableTable from '@/Components/DataTable/FilterableTable';
import RowActions from '@/Components/DataTable/RowActions';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { useDataTableQuery } from '@/hooks/useDataTableQuery';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

function CoinForm({ coin, onCancel }) {
    const form = useForm({
        codigo: coin?.codigo || '',
        descripcion: coin?.descripcion || '',
        habilitada: coin?.habilitada ?? true,
    });

    const submit = (e) => {
        e.preventDefault();
        const opts = { onSuccess: onCancel, preserveScroll: true };
        if (coin?.id) {
            form.put(route('crypto-coins.update', coin.id), opts);
        } else {
            form.post(route('crypto-coins.store'), opts);
        }
    };

    return (
        <form onSubmit={submit} className="bg-slate-50 border border-slate-200 rounded-lg p-4 mb-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            <div>
                <InputLabel value="Código" />
                <TextInput className="mt-1 block w-full" value={form.data.codigo} onChange={(e) => form.setData('codigo', e.target.value.toUpperCase())} />
                <InputError message={form.errors.codigo} />
            </div>
            <div>
                <InputLabel value="Descripción" />
                <TextInput className="mt-1 block w-full" value={form.data.descripcion} onChange={(e) => form.setData('descripcion', e.target.value)} />
                <InputError message={form.errors.descripcion} />
            </div>
            <div>
                <label className="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" checked={form.data.habilitada} onChange={(e) => form.setData('habilitada', e.target.checked)} />
                    Habilitada
                </label>
            </div>
            <div className="flex gap-2">
                <PrimaryButton disabled={form.processing}>Guardar</PrimaryButton>
                <button type="button" onClick={onCancel} className="text-sm text-slate-600 underline">Cancelar</button>
            </div>
        </form>
    );
}

export default function Index({ coins, filters: initialFilters, sort: initialSort, direction: initialDirection }) {
    const { flash } = usePage().props;
    const [editing, setEditing] = useState(null);
    const [creating, setCreating] = useState(false);
    const {
        filters, sort, direction, setFilter, clearFilters, toggleSort, hasActiveFilters,
    } = useDataTableQuery('crypto-coins.index', { filters: initialFilters, sort: initialSort, direction: initialDirection });

    const columns = [
        { key: 'codigo', header: 'Código', filterKey: 'codigo', sortKey: 'codigo', className: 'font-mono', render: (r) => r.codigo },
        { key: 'descripcion', header: 'Descripción', filterKey: 'descripcion', sortKey: 'descripcion', render: (r) => r.descripcion },
        {
            key: 'habilitada',
            header: 'Hab.',
            filterKey: 'habilitada',
            sortKey: 'habilitada',
            filterPlaceholder: 'sí/no',
            render: (r) => (r.habilitada ? 'Sí' : 'No'),
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
                    destroyRoute="crypto-coins.destroy"
                    destroyId={row.id}
                    destroyMessage="¿Eliminar coin?"
                />
            ),
        },
    ];

    return (
        <AdminLayout header="Coins (Crypto)">
            <Head title="Coins" />
            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{flash.success}</div>
            )}
            <div className="mb-4">
                <PrimaryButton onClick={() => { setCreating(true); setEditing(null); }}>Agregar</PrimaryButton>
            </div>
            {(creating || editing) && (
                <CoinForm coin={editing} onCancel={() => { setCreating(false); setEditing(null); }} />
            )}
            <FilterableTable
                columns={columns}
                rows={coins}
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
