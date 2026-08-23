import AdminLayout from '@/Layouts/AdminLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

function StatusBadge({ ok, label }) {
    return (
        <span
            className={`inline-flex items-center rounded px-2 py-0.5 text-xs font-medium ${
                ok
                    ? 'bg-emerald-50 text-emerald-800 border border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800'
                    : 'bg-red-50 text-red-800 border border-red-200 dark:bg-red-950/40 dark:text-red-300 dark:border-red-800'
            }`}
        >
            {label}
        </span>
    );
}

export default function Index({ status }) {
    const { flash } = usePage().props;
    const [running, setRunning] = useState(false);

    const runBackup = (disk = null) => {
        if (running) {
            return;
        }
        const msg = disk
            ? `¿Ejecutar backup solo hacia ${disk}?`
            : '¿Ejecutar backup de la base de datos hacia todos los destinos?';
        if (!confirm(msg)) {
            return;
        }
        setRunning(true);
        router.post(
            route('backup.run'),
            disk ? { disk } : {},
            {
                preserveScroll: true,
                onFinish: () => setRunning(false),
            },
        );
    };

    const lastRun = status.last_run;

    return (
        <AdminLayout header="Backup">
            <Head title="Backup" />

            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {flash.success}
                </div>
            )}
            {flash?.error && (
                <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                    {flash.error}
                </div>
            )}

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div className="text-sm text-slate-600 space-y-1">
                    <div>
                        Programado: diario <span className="font-mono">{status.schedule.backup_at}</span>
                        {' '}· limpieza <span className="font-mono">{status.schedule.clean_at}</span>
                    </div>
                    {status.last_ok && (
                        <div>
                            Último OK: <span className="font-mono">{status.last_ok}</span>
                        </div>
                    )}
                    {status.last_error && (
                        <div className="text-red-600">
                            Último error: {status.last_error}
                        </div>
                    )}
                </div>
                <PrimaryButton
                    type="button"
                    disabled={running}
                    onClick={() => runBackup(null)}
                >
                    {running ? 'Ejecutando…' : 'Ejecutar backup ahora'}
                </PrimaryButton>
            </div>

            {lastRun && (
                <div className="mb-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="flex flex-wrap items-center gap-2 mb-2">
                        <h3 className="text-sm font-medium text-slate-700">Última corrida</h3>
                        <StatusBadge ok={Boolean(lastRun.ok)} label={lastRun.ok ? 'OK' : 'Error'} />
                        {lastRun.triggered_by && (
                            <span className="text-xs text-slate-500">({lastRun.triggered_by})</span>
                        )}
                    </div>
                    <p className="text-sm text-slate-600 font-mono">{lastRun.at}</p>
                    {lastRun.message && (
                        <p className="text-sm text-slate-600 mt-1">{lastRun.message}</p>
                    )}
                    {Array.isArray(lastRun.disks) && lastRun.disks.length > 0 && (
                        <ul className="mt-3 space-y-1 text-sm">
                            {lastRun.disks.map((d) => (
                                <li key={d.disk} className="flex flex-wrap items-center gap-2">
                                    <StatusBadge ok={Boolean(d.uploaded_ok)} label={d.uploaded_ok ? 'Subido' : 'Falló'} />
                                    <span className="font-medium text-slate-700">{d.label}</span>
                                    <span className="text-slate-400 font-mono text-xs">{d.disk}</span>
                                    {d.error && <span className="text-red-600 text-xs">{d.error}</span>}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {(status.disks || []).map((disk) => (
                    <div key={disk.disk} className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="flex flex-wrap items-start justify-between gap-2 mb-3">
                            <div>
                                <h3 className="font-medium text-slate-800">{disk.label}</h3>
                                <p className="text-xs text-slate-500 font-mono">
                                    {disk.disk} · {disk.driver}
                                    {disk.host ? ` · ${disk.host}` : ''}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-1">
                                <StatusBadge ok={disk.reachable} label={disk.reachable ? 'Alcanzable' : 'Sin conexión'} />
                                <StatusBadge ok={disk.healthy} label={disk.healthy ? 'Saludable' : 'No saludable'} />
                            </div>
                        </div>

                        {disk.connection_error && (
                            <p className="text-sm text-red-600 mb-2">{disk.connection_error}</p>
                        )}
                        {disk.health_failures?.length > 0 && (
                            <ul className="text-xs text-amber-700 mb-2 list-disc pl-4">
                                {disk.health_failures.map((f) => (
                                    <li key={f}>{f}</li>
                                ))}
                            </ul>
                        )}

                        <dl className="grid grid-cols-2 gap-2 text-sm mb-3">
                            <div>
                                <dt className="text-slate-500 text-xs">Backups</dt>
                                <dd className="font-mono text-slate-800">{disk.backups_count}</dd>
                            </div>
                            <div>
                                <dt className="text-slate-500 text-xs">Último archivo</dt>
                                <dd className="font-mono text-slate-800 text-xs break-all">
                                    {disk.newest?.date || '—'}
                                </dd>
                            </div>
                            <div className="col-span-2">
                                <dt className="text-slate-500 text-xs">Tamaño / ruta</dt>
                                <dd className="font-mono text-slate-600 text-xs break-all">
                                    {disk.newest
                                        ? `${disk.newest.human_size} · ${disk.newest.path}`
                                        : '—'}
                                </dd>
                            </div>
                        </dl>

                        <button
                            type="button"
                            disabled={running}
                            onClick={() => runBackup(disk.disk)}
                            className="text-sm text-emerald-700 underline hover:text-emerald-900 disabled:opacity-50"
                        >
                            Backup solo a este destino
                        </button>
                    </div>
                ))}
            </div>

            {(status.disks || []).length === 0 && (
                <p className="text-sm text-slate-500">No hay discos configurados en BACKUP_DISKS.</p>
            )}
        </AdminLayout>
    );
}
