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

function WalletForm({ wallet, coins, onCancel }) {
    const form = useForm({
        codigo: wallet?.codigo || '',
        descripcion: wallet?.descripcion || '',
        id_coin: wallet?.id_coin || '',
        habilitada: wallet?.habilitada ?? true,
    });

    const submit = (e) => {
        e.preventDefault();
        const opts = { onSuccess: onCancel, preserveScroll: true };
        if (wallet?.id) {
            form.put(route('crypto-wallets.update', wallet.id), opts);
        } else {
            form.post(route('crypto-wallets.store'), opts);
        }
    };

    return (
        <form onSubmit={submit} className="bg-slate-50 border border-slate-200 rounded-lg p-4 mb-4 grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
            <div>
                <InputLabel value="Código / nombre" />
                <TextInput className="mt-1 block w-full" value={form.data.codigo} onChange={(e) => form.setData('codigo', e.target.value)} />
                <InputError message={form.errors.codigo} />
            </div>
            <div>
                <InputLabel value="Descripción" />
                <TextInput className="mt-1 block w-full" value={form.data.descripcion} onChange={(e) => form.setData('descripcion', e.target.value)} />
                <InputError message={form.errors.descripcion} />
            </div>
            <div>
                <InputLabel value="Coin" />
                <select
                    className="mt-1 block w-full rounded-md border-slate-300 text-sm"
                    value={form.data.id_coin}
                    onChange={(e) => form.setData('id_coin', e.target.value)}
                >
                    <option value="">—</option>
                    {(coins || []).map((c) => (
                        <option key={c.id} value={c.id}>{c.codigo}{c.descripcion ? ` — ${c.descripcion}` : ''}</option>
                    ))}
                </select>
                <InputError message={form.errors.id_coin} />
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

export default function Index({ wallets, coins, filters: initialFilters, sort: initialSort, direction: initialDirection }) {
    const { flash } = usePage().props;
    const [editing, setEditing] = useState(null);
    const [creating, setCreating] = useState(false);
    const {
        filters, sort, direction, setFilter, clearFilters, toggleSort, hasActiveFilters,
    } = useDataTableQuery('crypto-wallets.index', { filters: initialFilters, sort: initialSort, direction: initialDirection });

    const columns = [
        { key: 'codigo', header: 'Código', filterKey: 'codigo', sortKey: 'codigo', render: (r) => r.codigo },
        { key: 'descripcion', header: 'Descripción', filterKey: 'descripcion', sortKey: 'descripcion', render: (r) => r.descripcion },
        {
            key: 'coin',
            header: 'Coin',
            filterable: false,
            sortKey: 'coin',
            render: (r) => r.coin?.codigo || '—',
        },
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
                    destroyRoute="crypto-wallets.destroy"
                    destroyId={row.id}
                    destroyMessage="¿Eliminar wallet?"
                />
            ),
        },
    ];

    return (
        <AdminLayout header="Wallets (Crypto)">
            <Head title="Wallets" />
            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{flash.success}</div>
            )}
            <div className="mb-4">
                <PrimaryButton onClick={() => { setCreating(true); setEditing(null); }}>Agregar</PrimaryButton>
            </div>
            {(creating || editing) && (
                <WalletForm
                    wallet={editing}
                    coins={coins}
                    onCancel={() => { setCreating(false); setEditing(null); }}
                />
            )}
            <FilterableTable
                columns={columns}
                rows={wallets}
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
