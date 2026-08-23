import AdminLayout from '@/Layouts/AdminLayout';
import DateFilterInput from '@/Components/DateFilterInput';
import { Head, Link, router } from '@inertiajs/react';
import { CreditCard, Bitcoin, Scale } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

function formatMoney(value) {
    return Number(value || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatQty(value) {
    return Number(value || 0).toLocaleString('es-AR', { maximumFractionDigits: 8 });
}

function Skeleton({ className = '' }) {
    return <div className={`animate-pulse rounded bg-slate-200 dark:bg-slate-700 ${className}`} />;
}

function BarList({ items, valueKey = 'percent', formatValue, loading = false }) {
    if (loading) {
        return (
            <div className="space-y-3">
                {[0, 1, 2, 3].map((i) => (
                    <div key={i}>
                        <Skeleton className="mb-1 h-3 w-full" />
                        <Skeleton className="h-1.5 w-full" />
                    </div>
                ))}
            </div>
        );
    }

    const max = Math.max(...items.map((i) => Number(i[valueKey] || 0)), 0.0001);

    return (
        <div className="space-y-2">
            {items.length === 0 ? (
                <p className="text-sm text-slate-500">Sin datos.</p>
            ) : (
                items.map((item) => {
                    const raw = Number(item[valueKey] || 0);
                    const width = Math.max(0, Math.min(100, (raw / max) * 100));
                    return (
                        <div key={item.label}>
                            <div className="flex justify-between gap-2 text-xs text-slate-600 mb-0.5">
                                <span className="truncate">{item.label}</span>
                                <span className="shrink-0 font-mono tabular-nums">
                                    {formatValue ? formatValue(item) : `${raw.toFixed(1)}%`}
                                </span>
                            </div>
                            <div className="h-1.5 rounded bg-slate-100 overflow-hidden">
                                <div className="h-full rounded bg-emerald-500" style={{ width: `${width}%` }} />
                            </div>
                        </div>
                    );
                })
            )}
        </div>
    );
}

async function fetchJson(url) {
    const res = await fetch(url, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });
    if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
    }
    return res.json();
}

export default function Dashboard({ filters }) {
    const [hasta, setHasta] = useState(filters.hasta);
    const [cryptoCoin, setCryptoCoin] = useState(filters.crypto_coin || 'BTC');
    const [openWallets, setOpenWallets] = useState({});
    const [walletsByCoin, setWalletsByCoin] = useState({});
    const [loadingWallets, setLoadingWallets] = useState({});

    const [saldosData, setSaldosData] = useState(null);
    const [distData, setDistData] = useState(null);
    const [crypto, setCrypto] = useState(null);

    const [loadingSaldos, setLoadingSaldos] = useState(true);
    const [loadingDist, setLoadingDist] = useState(true);
    const [loadingCrypto, setLoadingCrypto] = useState(true);
    const [errorSaldos, setErrorSaldos] = useState(null);
    const [errorDist, setErrorDist] = useState(null);
    const [errorCrypto, setErrorCrypto] = useState(null);

    const saldosReq = useRef(0);
    const distReq = useRef(0);
    const cryptoReq = useRef(0);

    const loadSaldos = useCallback(async (date) => {
        const reqId = ++saldosReq.current;
        setLoadingSaldos(true);
        setErrorSaldos(null);
        try {
            const data = await fetchJson(route('dashboard.saldos', { hasta: date }));
            if (reqId !== saldosReq.current) return;
            setSaldosData(data);
        } catch {
            if (reqId !== saldosReq.current) return;
            setErrorSaldos('No se pudieron cargar los saldos.');
        } finally {
            if (reqId === saldosReq.current) setLoadingSaldos(false);
        }
    }, []);

    const loadDistribucion = useCallback(async (date) => {
        const reqId = ++distReq.current;
        setLoadingDist(true);
        setErrorDist(null);
        try {
            const data = await fetchJson(route('dashboard.distribucion', { hasta: date }));
            if (reqId !== distReq.current) return;
            setDistData(data);
        } catch {
            if (reqId !== distReq.current) return;
            setErrorDist('No se pudieron cargar las distribuciones.');
        } finally {
            if (reqId === distReq.current) setLoadingDist(false);
        }
    }, []);

    const loadCrypto = useCallback(async (coin) => {
        const reqId = ++cryptoReq.current;
        setLoadingCrypto(true);
        setErrorCrypto(null);
        setWalletsByCoin({});
        try {
            const data = await fetchJson(route('dashboard.crypto', { crypto_coin: coin }));
            if (reqId !== cryptoReq.current) return;
            setCrypto(data);
        } catch {
            if (reqId !== cryptoReq.current) return;
            setErrorCrypto('No se pudo cargar crypto.');
        } finally {
            if (reqId === cryptoReq.current) setLoadingCrypto(false);
        }
    }, []);

    const loadWallets = useCallback(async (coin) => {
        const key = String(coin).toUpperCase();
        setLoadingWallets((prev) => ({ ...prev, [key]: true }));
        try {
            const data = await fetchJson(route('dashboard.crypto.wallets', { crypto_coin: key }));
            setWalletsByCoin((prev) => ({ ...prev, [key]: data.crypto_wallets || [] }));
        } catch {
            setWalletsByCoin((prev) => ({ ...prev, [key]: [] }));
        } finally {
            setLoadingWallets((prev) => ({ ...prev, [key]: false }));
        }
    }, []);

    useEffect(() => {
        setHasta(filters.hasta);
        setCryptoCoin(filters.crypto_coin || 'BTC');
    }, [filters.hasta, filters.crypto_coin]);

    useEffect(() => {
        loadSaldos(hasta);
        loadDistribucion(hasta);
    }, [hasta, loadSaldos, loadDistribucion]);

    useEffect(() => {
        loadCrypto(cryptoCoin);
    }, [cryptoCoin, loadCrypto]);

    const syncUrl = (nextHasta, nextCoin) => {
        router.get(
            route('dashboard'),
            { hasta: nextHasta, crypto_coin: nextCoin },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    };

    const changeHasta = (value) => {
        if (!value) {
            return;
        }
        setHasta(value);
        syncUrl(value, cryptoCoin);
    };

    const changeCryptoCoin = (value) => {
        setCryptoCoin(value);
        setOpenWallets({});
        syncUrl(hasta, value);
    };

    const toggleWallets = (coin) => {
        const key = String(coin).toUpperCase();
        setOpenWallets((prev) => {
            const nextOpen = !prev[key];
            if (nextOpen && walletsByCoin[key] == null) {
                loadWallets(key);
            }
            return { ...prev, [key]: nextOpen };
        });
    };

    const saldos = saldosData?.saldos || [];
    const cryptoRows = crypto?.crypto || [];
    const cryptoCoins = crypto?.crypto_coins || [];

    return (
        <AdminLayout header="Inicio">
            <Head title="Inicio" />

            <div className="flex flex-wrap items-end gap-3 mb-4">
                <div>
                    <label className="block text-xs text-slate-500 mb-1">Saldos hasta</label>
                    <DateFilterInput value={hasta} onCommit={changeHasta} />
                </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-4">
                {loadingSaldos && !saldosData ? (
                    [0, 1, 2, 3].map((i) => (
                        <div key={i} className="bg-white rounded-lg border border-slate-200 p-4 shadow-sm space-y-2">
                            <Skeleton className="h-3 w-20" />
                            <Skeleton className="h-7 w-32" />
                            <Skeleton className="h-3 w-28" />
                        </div>
                    ))
                ) : errorSaldos && !saldosData ? (
                    <div className="sm:col-span-2 xl:col-span-4 text-sm text-red-600">{errorSaldos}</div>
                ) : (
                    saldos.map((saldo) => (
                        <div key={saldo.codigo} className={`bg-white rounded-lg border border-slate-200 p-4 shadow-sm ${loadingSaldos ? 'opacity-60' : ''}`}>
                            <div className="text-xs text-slate-500">{saldo.simbolo} ({saldo.codigo})</div>
                            <div className="mt-1 text-xl font-semibold text-slate-800 font-mono tabular-nums">
                                {formatMoney(saldo.value)}
                            </div>
                            <div className="mt-1 text-xs text-slate-500">
                                Total equiv.: <span className="font-mono">{formatMoney(saldo.total)}</span>
                            </div>
                            {saldo.cotizacion != null && Number(saldo.cotizacion) !== 1 && (
                                <div className="mt-1 text-xs text-slate-400">
                                    Cotiz.: <span className="font-mono">{formatMoney(saldo.cotizacion)}</span>
                                </div>
                            )}
                        </div>
                    ))
                )}
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                <Link
                    href={route('tarjetas.index')}
                    className="flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800 hover:bg-red-100 dark:border-red-800 dark:bg-red-950/60 dark:text-red-300 dark:hover:bg-red-900/80"
                >
                    <CreditCard className="h-5 w-5" />
                    <span className="font-medium">Tarjetas</span>
                </Link>
                <Link
                    href={route('informes.balance')}
                    className="flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 hover:bg-emerald-100"
                >
                    <Scale className="h-5 w-5" />
                    <span className="font-medium">Balance</span>
                </Link>
                <Link
                    href={route('pagos.index')}
                    className="rounded-lg border border-slate-200 bg-white px-4 py-3 text-slate-700 hover:bg-slate-50 font-medium"
                >
                    Pagos
                </Link>
                <Link
                    href={route('ingresos.index')}
                    className="rounded-lg border border-slate-200 bg-white px-4 py-3 text-slate-700 hover:bg-slate-50 font-medium"
                >
                    Ingresos
                </Link>
            </div>

            <div className="bg-white rounded-lg border border-slate-200 p-4 shadow-sm mb-4">
                <div className="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <h3 className="text-sm font-medium text-slate-700 inline-flex items-center gap-2">
                        <Bitcoin className="h-4 w-4" />
                        Crypto
                    </h3>
                    <div className="flex flex-wrap items-center gap-3 text-sm">
                        <select
                            className="rounded border-slate-300 text-sm"
                            value={cryptoCoin}
                            onChange={(e) => changeCryptoCoin(e.target.value)}
                            disabled={loadingCrypto && !crypto}
                        >
                            {Array.from(new Set(['BTC', ...cryptoCoins, cryptoCoin])).map((c) => (
                                <option key={c} value={c}>{c}</option>
                            ))}
                        </select>
                        <Link href={route('crypto.index', { coin_eq: cryptoCoin })} className="text-emerald-700 underline">
                            Transacciones
                        </Link>
                        <Link href={route('informes.crypto', { coin: cryptoCoin })} className="text-emerald-700 underline">
                            Informe
                        </Link>
                    </div>
                </div>
                {loadingCrypto && !crypto ? (
                    <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                        {[0, 1].map((i) => (
                            <div key={i} className="rounded-md border border-slate-100 bg-slate-50/80 p-3 space-y-2">
                                <Skeleton className="h-4 w-16" />
                                <Skeleton className="h-3 w-full" />
                                <Skeleton className="h-3 w-3/4" />
                                <Skeleton className="h-3 w-2/3" />
                            </div>
                        ))}
                    </div>
                ) : errorCrypto && !crypto ? (
                    <p className="text-sm text-red-600">{errorCrypto}</p>
                ) : cryptoRows.length === 0 ? (
                    <p className="text-sm text-slate-500">Sin movimientos para {cryptoCoin}.</p>
                ) : (
                    <div className={`grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 ${loadingCrypto ? 'opacity-60' : ''}`}>
                        {cryptoRows.map((row) => {
                            const key = String(row.coin).toUpperCase();
                            const expanded = Boolean(openWallets[key]);
                            const wallets = walletsByCoin[key];
                            const walletsLoading = Boolean(loadingWallets[key]);
                            return (
                                <div key={row.coin} className="rounded-md border border-slate-100 bg-slate-50/80 p-3">
                                    <div className="font-semibold text-slate-800">{row.coin}</div>
                                    <div className="mt-1 text-sm text-slate-600">
                                        Cantidad: <span className="font-mono tabular-nums">{formatQty(row.quantity)}</span>
                                    </div>
                                    <div className="text-sm text-slate-600">
                                        Precio actual: <span className="font-mono">${formatMoney(row.price_usd_actual)}</span>
                                    </div>
                                    <div className="text-sm text-slate-600">
                                        Valor actual: <span className="font-mono">${formatMoney(row.value_usd)}</span>
                                        {row.value_ars != null && (
                                            <span className="text-slate-400"> · ${formatMoney(row.value_ars)} ARS</span>
                                        )}
                                    </div>
                                    <div className="text-sm text-slate-600">
                                        Inversión: <span className="font-mono">${formatMoney(row.investment_usd)}</span>
                                        <span className="text-slate-400"> (prom. ${formatMoney(row.price_usd_compra)})</span>
                                    </div>
                                    {row.pnl_usd != null && (
                                        <div className={`text-sm font-medium ${Number(row.pnl_usd) >= 0 ? 'text-emerald-700' : 'text-red-700'}`}>
                                            PnL: <span className="font-mono">${formatMoney(row.pnl_usd)}</span>
                                        </div>
                                    )}
                                    <button
                                        type="button"
                                        onClick={() => toggleWallets(row.coin)}
                                        className="text-xs text-emerald-700 mt-1 underline hover:text-emerald-900"
                                    >
                                        {row.wallets} wallet(s){expanded ? ' ▾' : ' ▸'}
                                    </button>
                                    {expanded && (
                                        <div className="mt-2 space-y-2 border-t border-slate-200 pt-2">
                                            {walletsLoading && wallets == null ? (
                                                <p className="text-xs text-slate-500">Cargando wallets…</p>
                                            ) : !wallets || wallets.length === 0 ? (
                                                <p className="text-xs text-slate-500">Sin wallets.</p>
                                            ) : (
                                                wallets.map((w) => (
                                                    <div key={`${w.coin}-${w.wallet}`} className="text-xs text-slate-600">
                                                        <div className="font-medium text-slate-700">{w.wallet || '(sin wallet)'}</div>
                                                        <div className="font-mono tabular-nums">
                                                            {formatQty(w.quantity)} · valor ${formatMoney(w.value_usd)}
                                                        </div>
                                                        <div className="text-slate-400">
                                                            Inversión ${formatMoney(w.investment_usd)}
                                                            {w.pnl_usd != null && (
                                                                <span className={Number(w.pnl_usd) >= 0 ? ' text-emerald-700' : ' text-red-700'}>
                                                                    {' '}· PnL ${formatMoney(w.pnl_usd)}
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                ))
                                            )}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                {errorDist && !distData && (
                    <div className="lg:col-span-3 text-sm text-red-600">{errorDist}</div>
                )}
                <div className="bg-white rounded-lg border border-slate-200 p-4 shadow-sm">
                    <h3 className="text-sm font-medium text-slate-700 mb-3">Distribución por moneda (%)</h3>
                    <BarList items={distData?.distribucion_moneda || []} loading={loadingDist && !distData} />
                </div>
                <div className="bg-white rounded-lg border border-slate-200 p-4 shadow-sm">
                    <h3 className="text-sm font-medium text-slate-700 mb-3">Distribución ARS</h3>
                    <BarList
                        items={distData?.distribucion_cuenta_ars || []}
                        valueKey="value"
                        formatValue={(i) => formatMoney(i.value)}
                        loading={loadingDist && !distData}
                    />
                </div>
                <div className="bg-white rounded-lg border border-slate-200 p-4 shadow-sm">
                    <h3 className="text-sm font-medium text-slate-700 mb-3">Distribución USD</h3>
                    <BarList
                        items={distData?.distribucion_cuenta_usd || []}
                        valueKey="value"
                        formatValue={(i) => formatMoney(i.value)}
                        loading={loadingDist && !distData}
                    />
                </div>
            </div>
        </AdminLayout>
    );
}
