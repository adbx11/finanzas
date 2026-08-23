import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, usePage } from '@inertiajs/react';

export default function Index({ roles }) {
    const { flash } = usePage().props;

    return (
        <AdminLayout header="Roles">
            <Head title="Roles" />

            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{flash.success}</div>
            )}

            <p className="text-sm text-slate-600 mb-4">
                Roles simplificados (Spatie). La asignación se hace desde{' '}
                <Link href={route('usuarios.index')} className="text-emerald-700 underline">Usuarios</Link>.
            </p>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                {(roles || []).map((role) => (
                    <div key={role.id} className="bg-white rounded-lg border border-slate-200 p-4 shadow-sm">
                        <div className="font-semibold text-slate-800 font-mono">{role.name}</div>
                        <p className="mt-2 text-sm text-slate-600">{role.description}</p>
                        <div className="mt-3 text-xs text-slate-500">
                            {role.active_users_count} activos / {role.users_count} total
                        </div>
                        <Link
                            href={route('usuarios.index', { filters: { role: role.name } })}
                            className="mt-3 inline-block text-sm text-emerald-700 underline"
                        >
                            Ver usuarios
                        </Link>
                    </div>
                ))}
            </div>
        </AdminLayout>
    );
}
