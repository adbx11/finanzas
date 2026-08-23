import AdminLayout from '@/Layouts/AdminLayout';
import CuentaSelect from '@/Components/CuentaSelect';
import DateFilterInput, { shiftDate } from '@/Components/DateFilterInput';
import { formatDateAR } from '@/utils/dateFormat';
import { Head, Link, router } from '@inertiajs/react';

function formatMoney(value) {
    return Number(value || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function Mayor({ mayor, cuentas, filters }) {
    const apply = (next) => {
        router.get(route('informes.mayor'), {
            id_cuenta: next.id_cuenta || undefined,
            desde: next.desde,
            hasta: next.hasta,
        }, { preserveState: true, replace: true });
    };

    const showOrigen = mayor && !mayor.es_moneda_local;

    return (
        <AdminLayout header="Mayor">
            <Head title="Mayor" />

            <div className="flex flex-wrap items-end gap-3 mb-4">
                <div className="min-w-[16rem] flex-1">
                    <label className="block text-xs text-slate-500 mb-1">Cuenta</label>
                    <CuentaSelect
                        cuentas={cuentas}
                        value={filters.id_cuenta || ''}
                        onChange={(id) => apply({ ...filters, id_cuenta: id })}
                        placeholder="Seleccionar cuenta..."
                    />
                </div>
                <div>
                    <label className="block text-xs text-slate-500 mb-1">Desde</label>
                    <div className="flex items-center gap-1">
                        <button type="button" className="px-1 border rounded text-sm" onClick={() => apply({ ...filters, desde: shiftDate(filters.desde, 'd', -1) })}>−d</button>
                        <DateFilterInput value={filters.desde || ''} onCommit={(desde) => apply({ ...filters, desde })} />
                        <button type="button" className="px-1 border rounded text-sm" onClick={() => apply({ ...filters, desde: shiftDate(filters.desde, 'd', 1) })}>+d</button>
                    </div>
                </div>
                <div>
                    <label className="block text-xs text-slate-500 mb-1">Hasta</label>
                    <div className="flex items-center gap-1">
                        <button type="button" className="px-1 border rounded text-sm" onClick={() => apply({ ...filters, hasta: shiftDate(filters.hasta, 'd', -1) })}>−d</button>
                        <DateFilterInput value={filters.hasta || ''} onCommit={(hasta) => apply({ ...filters, hasta })} />
                        <button type="button" className="px-1 border rounded text-sm" onClick={() => apply({ ...filters, hasta: shiftDate(filters.hasta, 'd', 1) })}>+d</button>
                    </div>
                </div>
            </div>

            {!mayor ? (
                <div className="bg-white border border-slate-200 rounded-lg p-8 text-center text-slate-500">
                    Seleccioná una cuenta para ver el mayor.
                </div>
            ) : (
                <div className="space-y-3">
                    <div className="text-sm text-slate-600">
                        <span className="font-medium text-slate-800">{mayor.cuenta.codigo} {mayor.cuenta.descripcion}</span>
                        {mayor.cuenta.moneda && (
                            <span className="ml-2">({mayor.cuenta.moneda.simbolo})</span>
                        )}
                    </div>

                    <div className="bg-white shadow-sm border border-slate-200 overflow-x-auto -mx-4 sm:mx-0 rounded-none sm:rounded-lg border-x-0 sm:border-x">
                        <table className="min-w-[900px] w-full text-sm">
                            <thead className="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th className="px-2 py-2 text-left">Fecha</th>
                                    <th className="px-2 py-2 text-left">Descripción</th>
                                    <th className="px-2 py-2 text-right">Un.</th>
                                    <th className="px-2 py-2 text-right">Debe</th>
                                    <th className="px-2 py-2 text-right">Haber</th>
                                    <th className="px-2 py-2 text-right">Saldo</th>
                                    {showOrigen && (
                                        <>
                                            <th className="px-2 py-2 text-right">Debe orig.</th>
                                            <th className="px-2 py-2 text-right">Haber orig.</th>
                                            <th className="px-2 py-2 text-right">Saldo orig.</th>
                                        </>
                                    )}
                                    <th className="px-2 py-2 text-right">Asiento</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                <tr className="bg-amber-50/50">
                                    <td className="px-2 py-1.5" />
                                    <td className="px-2 py-1.5 font-medium">Saldo anterior</td>
                                    <td className="px-2 py-1.5 text-right font-mono">{mayor.saldo_anterior.unidades}</td>
                                    <td className="px-2 py-1.5" />
                                    <td className="px-2 py-1.5" />
                                    <td className="px-2 py-1.5 text-right font-mono">{formatMoney(mayor.saldo_anterior.saldo)}</td>
                                    {showOrigen && (
                                        <>
                                            <td className="px-2 py-1.5" />
                                            <td className="px-2 py-1.5" />
                                            <td className="px-2 py-1.5 text-right font-mono">{formatMoney(mayor.saldo_anterior.saldo_origen)}</td>
                                        </>
                                    )}
                                    <td className="px-2 py-1.5" />
                                </tr>
                                {mayor.movimientos.map((m, index) => (
                                    <tr
                                        key={`${m.id_asiento}-${m.fecha}-${m.debe}-${m.haber}`}
                                        className={`${index % 2 === 1 ? 'bg-slate-50/80' : 'bg-white'} hover:bg-emerald-50/70 transition-colors`}
                                    >
                                        <td className="px-2 py-1.5 whitespace-nowrap">{formatDateAR(m.fecha)}</td>
                                        <td className="px-2 py-1.5">{m.descripcion}</td>
                                        <td className="px-2 py-1.5 text-right font-mono">{m.unidades || ''}</td>
                                        <td className="px-2 py-1.5 text-right font-mono">{Number(m.debe) ? formatMoney(m.debe) : ''}</td>
                                        <td className="px-2 py-1.5 text-right font-mono">{Number(m.haber) ? formatMoney(m.haber) : ''}</td>
                                        <td className="px-2 py-1.5 text-right font-mono">{formatMoney(m.saldo)}</td>
                                        {showOrigen && (
                                            <>
                                                <td className="px-2 py-1.5 text-right font-mono">{Number(m.debe_origen) ? formatMoney(m.debe_origen) : ''}</td>
                                                <td className="px-2 py-1.5 text-right font-mono">{Number(m.haber_origen) ? formatMoney(m.haber_origen) : ''}</td>
                                                <td className="px-2 py-1.5 text-right font-mono">{formatMoney(m.saldo_origen)}</td>
                                            </>
                                        )}
                                        <td className="px-2 py-1.5 text-right">
                                            <Link href={route('asientos.edit', m.id_asiento)} className="text-emerald-700 hover:underline">
                                                {m.id_asiento}
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="bg-slate-50 border-t border-slate-200 font-medium">
                                <tr>
                                    <td className="px-2 py-2" colSpan={2}>Totales</td>
                                    <td className="px-2 py-2 text-right font-mono">{mayor.totales.unidades}</td>
                                    <td className="px-2 py-2 text-right font-mono">{formatMoney(mayor.totales.debe)}</td>
                                    <td className="px-2 py-2 text-right font-mono">{formatMoney(mayor.totales.haber)}</td>
                                    <td className="px-2 py-2 text-right font-mono">{formatMoney(mayor.totales.saldo)}</td>
                                    {showOrigen && (
                                        <>
                                            <td className="px-2 py-2 text-right font-mono">{formatMoney(mayor.totales.debe_origen)}</td>
                                            <td className="px-2 py-2 text-right font-mono">{formatMoney(mayor.totales.haber_origen)}</td>
                                            <td className="px-2 py-2 text-right font-mono">{formatMoney(mayor.totales.saldo_origen)}</td>
                                        </>
                                    )}
                                    <td className="px-2 py-2" />
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
