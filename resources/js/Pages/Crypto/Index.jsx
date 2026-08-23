import AdminLayout from '@/Layouts/AdminLayout';
import FilterableTable from '@/Components/DataTable/FilterableTable';
import RowActions from '@/Components/DataTable/RowActions';
import DecimalInput from '@/Components/DecimalInput';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { useDataTableQuery } from '@/hooks/useDataTableQuery';
import { usePersistedListState } from '@/hooks/usePersistedListState';
import { formatDateAR } from '@/utils/dateFormat';
import { formatDecimalInput } from '@/utils/decimalInput';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

function toDateTimeLocal(value) {
    if (!value) {
        return new Date().toISOString().slice(0, 16);
    }
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) {
        return String(value).replace(' ', 'T').slice(0, 16);
    }
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function TransactionForm({ transaction, coins, wallets, onCancel, coinEq }) {
    const isEdit = Boolean(transaction?.id);
    const defaultCoin = transaction?.coin || coinEq || coins?.[0]?.codigo || '';
    const form = useForm({
        coin: defaultCoin,
        wallet: transaction?.wallet || '',
        quantity: formatDecimalInput(transaction?.quantity, 8) || '',
        ts: toDateTimeLocal(transaction?.ts),
        price_usd: formatDecimalInput(transaction?.price_usd, 2) || '',
        price_btc: formatDecimalInput(transaction?.price_btc, 8) || '',
    });

    const walletsForCoin = (wallets || []).filter((w) => {
        if (!form.data.coin) {
            return true;
        }
        if (!w.id_coin || !w.coin) {
            return true;
        }
        return String(w.coin).toUpperCase() === String(form.data.coin).toUpperCase();
    });

    const submit = (e) => {
        e.preventDefault();
        const options = {
            onSuccess: onCancel,
            preserveScroll: true,
        };
        if (isEdit) {
            form.put(route('crypto.update', transaction.id), options);
        } else {
            form.post(route('crypto.store'), options);
        }
    };

    return (
        <form onSubmit={submit} className="bg-slate-50 border border-slate-200 rounded-lg p-4 mb-4 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3 items-end">
            <div>
                <InputLabel value="Coin" />
                <select
                    className="mt-1 block w-full rounded-md border-slate-300 text-sm"
                    value={form.data.coin}
                    onChange={(e) => {
                        const next = e.target.value;
                        form.setData((data) => {
                            const stillValid = (wallets || []).some((w) => {
                                if (w.codigo !== data.wallet) {
                                    return false;
                                }
                                if (!w.id_coin || !w.coin) {
                                    return true;
                                }
                                return String(w.coin).toUpperCase() === String(next).toUpperCase();
                            });
                            return {
                                ...data,
                                coin: next,
                                wallet: stillValid ? data.wallet : '',
                            };
                        });
                    }}
                >
                    <option value="">—</option>
                    {(coins || []).map((c) => (
                        <option key={c.id || c.codigo} value={c.codigo}>
                            {c.codigo}{c.descripcion && c.descripcion !== c.codigo ? ` — ${c.descripcion}` : ''}
                        </option>
                    ))}
                </select>
                <InputError message={form.errors.coin} />
            </div>
            <div>
                <InputLabel value="Wallet" />
                <select
                    className="mt-1 block w-full rounded-md border-slate-300 text-sm"
                    value={form.data.wallet}
                    onChange={(e) => form.setData('wallet', e.target.value)}
                >
                    <option value="">—</option>
                    {walletsForCoin.map((w) => (
                        <option key={w.id || w.codigo} value={w.codigo}>
                            {w.codigo}{w.descripcion && w.descripcion !== w.codigo ? ` — ${w.descripcion}` : ''}
                        </option>
                    ))}
                </select>
                <InputError message={form.errors.wallet} />
            </div>
            <div>
                <InputLabel value="Cantidad" />
                <DecimalInput className="mt-1 block w-full" decimals={8} value={form.data.quantity} onChange={(v) => form.setData('quantity', v)} />
                <InputError message={form.errors.quantity} />
            </div>
            <div>
                <InputLabel value="Fecha/hora" />
                <TextInput type="datetime-local" className="mt-1 block w-full" value={form.data.ts} onChange={(e) => form.setData('ts', e.target.value)} />
                <InputError message={form.errors.ts} />
            </div>
            <div>
                <InputLabel value="Precio USD" />
                <DecimalInput className="mt-1 block w-full" decimals={2} value={form.data.price_usd} onChange={(v) => form.setData('price_usd', v)} />
                <InputError message={form.errors.price_usd} />
            </div>
            <div>
                <InputLabel value="Precio BTC" />
                <DecimalInput className="mt-1 block w-full" decimals={8} value={form.data.price_btc} onChange={(v) => form.setData('price_btc', v)} />
                <InputError message={form.errors.price_btc} />
            </div>
            <div className="md:col-span-3 lg:col-span-6 flex gap-3">
                <PrimaryButton disabled={form.processing}>{isEdit ? 'Actualizar' : 'Guardar'}</PrimaryButton>
                <button type="button" onClick={onCancel} className="text-sm text-slate-600 underline self-center">Cancelar</button>
            </div>
        </form>
    );
}

function formatQty(value) {
    const n = Number(value || 0);
    return n.toLocaleString('es-AR', { maximumFractionDigits: 8 });
}

function formatMoney(value) {
    return Number(value || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function Index({
    transactions,
    filters: initialFilters,
    sort: initialSort,
    direction: initialDirection,
    coinEq,
    coins,
    wallets,
}) {
    const { flash } = usePage().props;
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
        queryParams,
    } = useDataTableQuery(
        'crypto.index',
        { filters: initialFilters, sort: initialSort, direction: initialDirection },
        { coin_eq: coinEq || '' },
    );

    usePersistedListState('crypto.index', {
        ...queryParams,
        coin_eq: coinEq || '',
        page: transactions.current_page,
    });

    const closeForm = () => {
        setCreating(false);
        setEditing(null);
        setCopying(null);
    };

    const changeCoin = (value) => {
        router.get(route('crypto.index'), { ...queryParams, coin_eq: value || undefined, page: 1 }, { preserveState: true, replace: true });
    };

    const columns = [
        {
            key: 'ts',
            header: 'Fecha',
            filterKey: 'ts',
            sortKey: 'ts',
            render: (row) => {
                const raw = String(row.ts || '');
                const datePart = raw.includes('T') ? raw.slice(0, 10) : raw.slice(0, 10);
                const timePart = raw.includes('T') ? raw.slice(11, 16) : (raw.includes(' ') ? raw.slice(11, 16) : '');
                return `${formatDateAR(datePart)}${timePart ? ` ${timePart}` : ''}`;
            },
        },
        {
            key: 'coin',
            header: 'Coin',
            filterKey: 'coin',
            sortKey: 'coin',
            render: (row) => row.coin,
        },
        {
            key: 'wallet',
            header: 'Wallet',
            filterKey: 'wallet',
            sortKey: 'wallet',
            render: (row) => row.wallet,
        },
        {
            key: 'quantity',
            header: 'Cantidad',
            filterKey: 'quantity',
            sortKey: 'quantity',
            align: 'right',
            className: 'font-mono',
            render: (row) => formatQty(row.quantity),
        },
        {
            key: 'price_usd',
            header: 'USD',
            filterKey: 'price_usd',
            sortKey: 'price_usd',
            align: 'right',
            className: 'font-mono',
            render: (row) => formatMoney(row.price_usd),
        },
        {
            key: 'price_btc',
            header: 'BTC',
            filterKey: 'price_btc',
            sortKey: 'price_btc',
            align: 'right',
            className: 'font-mono',
            render: (row) => formatQty(row.price_btc),
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
                    destroyRoute="crypto.destroy"
                    destroyId={row.id}
                    destroyMessage="¿Eliminar transacción?"
                />
            ),
        },
    ];

    const formTx = editing || copying;

    return (
        <AdminLayout header="Crypto">
            <Head title="Crypto" />

            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {flash.success}
                </div>
            )}

            <div className="flex flex-wrap items-center gap-3 mb-4">
                <PrimaryButton onClick={() => { setCreating(true); setEditing(null); setCopying(null); }}>
                    Agregar
                </PrimaryButton>
                <select
                    className="rounded border-slate-300 text-sm"
                    value={coinEq || ''}
                    onChange={(e) => changeCoin(e.target.value)}
                >
                    <option value="">Todas las coins</option>
                    {(coins || []).map((c) => (
                        <option key={c.id || c.codigo} value={c.codigo}>{c.codigo}</option>
                    ))}
                </select>
            </div>

            {(creating || editing) && (
                <TransactionForm
                    transaction={formTx}
                    coins={coins}
                    wallets={wallets}
                    coinEq={coinEq}
                    onCancel={closeForm}
                />
            )}

            <FilterableTable
                columns={columns}
                rows={transactions.data}
                filters={filters}
                onFilterChange={setFilter}
                onClearFilters={clearFilters}
                hasActiveFilters={hasActiveFilters}
                sort={sort}
                direction={direction}
                onSortChange={toggleSort}
                pagination={transactions}
                onPageChange={goToPage}
            />
        </AdminLayout>
    );
}
