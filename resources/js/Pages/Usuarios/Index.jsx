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

function UserForm({ user, roles, onCancel }) {
    const isEdit = Boolean(user?.id);
    const form = useForm({
        username: user?.username || '',
        name: user?.name || '',
        email: user?.email || '',
        role: user?.role || roles?.[0] || 'operador',
        active: user?.active ?? true,
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        const opts = { onSuccess: onCancel, preserveScroll: true };
        if (isEdit) {
            form.put(route('usuarios.update', user.id), opts);
        } else {
            form.post(route('usuarios.store'), opts);
        }
    };

    return (
        <form onSubmit={submit} className="bg-slate-50 border border-slate-200 rounded-lg p-4 mb-4 grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
            <div>
                <InputLabel value="Usuario" />
                <TextInput className="mt-1 block w-full" value={form.data.username} onChange={(e) => form.setData('username', e.target.value)} autoComplete="off" />
                <InputError message={form.errors.username} />
            </div>
            <div>
                <InputLabel value="Nombre" />
                <TextInput className="mt-1 block w-full" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                <InputError message={form.errors.name} />
            </div>
            <div>
                <InputLabel value="Email" />
                <TextInput type="email" className="mt-1 block w-full" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                <InputError message={form.errors.email} />
            </div>
            <div>
                <InputLabel value="Rol" />
                <select
                    className="mt-1 block w-full rounded-md border-slate-300 text-sm"
                    value={form.data.role}
                    onChange={(e) => form.setData('role', e.target.value)}
                >
                    {(roles || []).map((r) => (
                        <option key={r} value={r}>{r}</option>
                    ))}
                </select>
                <InputError message={form.errors.role} />
            </div>
            <div>
                <InputLabel value={isEdit ? 'Nueva contraseña (opcional)' : 'Contraseña'} />
                <TextInput
                    type="password"
                    className="mt-1 block w-full"
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                    autoComplete="new-password"
                />
                <InputError message={form.errors.password} />
            </div>
            <div>
                <InputLabel value="Confirmar contraseña" />
                <TextInput
                    type="password"
                    className="mt-1 block w-full"
                    value={form.data.password_confirmation}
                    onChange={(e) => form.setData('password_confirmation', e.target.value)}
                    autoComplete="new-password"
                />
            </div>
            <div>
                <label className="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" checked={form.data.active} onChange={(e) => form.setData('active', e.target.checked)} />
                    Activo
                </label>
                <InputError message={form.errors.active} />
            </div>
            <div className="md:col-span-2 flex gap-2">
                <PrimaryButton disabled={form.processing}>Guardar</PrimaryButton>
                <button type="button" onClick={onCancel} className="text-sm text-slate-600 underline">Cancelar</button>
            </div>
            {form.errors.user && <InputError className="md:col-span-3" message={form.errors.user} />}
        </form>
    );
}

export default function Index({ users, roles, filters: initialFilters, sort: initialSort, direction: initialDirection }) {
    const { flash } = usePage().props;
    const [editing, setEditing] = useState(null);
    const [creating, setCreating] = useState(false);
    const {
        filters, sort, direction, setFilter, clearFilters, toggleSort, hasActiveFilters,
    } = useDataTableQuery('usuarios.index', { filters: initialFilters, sort: initialSort, direction: initialDirection });

    const columns = [
        { key: 'username', header: 'Usuario', filterKey: 'username', sortKey: 'username', render: (r) => r.username },
        { key: 'name', header: 'Nombre', filterKey: 'name', sortKey: 'name', render: (r) => r.name },
        { key: 'email', header: 'Email', filterKey: 'email', sortKey: 'email', render: (r) => r.email },
        {
            key: 'role',
            header: 'Rol',
            filterKey: 'role',
            filterable: true,
            sortable: false,
            filterPlaceholder: 'admin/operador/lectura',
            render: (r) => r.role || '—',
        },
        {
            key: 'active',
            header: 'Activo',
            filterKey: 'active',
            sortKey: 'active',
            filterPlaceholder: 'sí/no',
            render: (r) => (r.active ? 'Sí' : 'No'),
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
                    destroyRoute="usuarios.destroy"
                    destroyId={row.id}
                    destroyMessage={`¿Eliminar usuario ${row.username}?`}
                />
            ),
        },
    ];

    return (
        <AdminLayout header="Usuarios">
            <Head title="Usuarios" />

            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{flash.success}</div>
            )}
            {flash?.error && (
                <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{flash.error}</div>
            )}

            <div className="mb-4">
                <PrimaryButton onClick={() => { setCreating(true); setEditing(null); }}>Agregar</PrimaryButton>
            </div>

            {(creating || editing) && (
                <UserForm
                    user={editing}
                    roles={roles}
                    onCancel={() => { setCreating(false); setEditing(null); }}
                />
            )}

            <FilterableTable
                columns={columns}
                rows={users}
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
