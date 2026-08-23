<?php

namespace App\Http\Controllers;

use App\Http\Concerns\AppliesColumnFilters;
use App\Http\Concerns\AppliesTableSorting;
use App\Models\CoinTransaction;
use App\Services\Crypto\CryptoService;
use App\Support\ListPagination;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CoinTransactionController extends Controller
{
    use AppliesColumnFilters;
    use AppliesTableSorting;

    public function __construct(
        private CryptoService $cryptoService,
    ) {}

    public function index(Request $request): Response
    {
        $query = CoinTransaction::query();

        $this->applyLikeFilter($query, $this->filterString($request, 'coin'), 'coin');
        $this->applyLikeFilter($query, $this->filterString($request, 'wallet'), 'wallet');
        $this->applyLikeFilter($query, $this->filterString($request, 'quantity'), 'quantity');
        $this->applyLikeFilter($query, $this->filterString($request, 'ts'), 'ts');
        $this->applyLikeFilter($query, $this->filterString($request, 'price_usd'), 'price_usd');
        $this->applyLikeFilter($query, $this->filterString($request, 'price_btc'), 'price_btc');

        if ($request->filled('coin_eq')) {
            $query->whereRaw('UPPER(coin) = ?', [strtoupper((string) $request->input('coin_eq'))]);
        }

        $this->applyTableSorting($query, $request, [
            'ts' => fn ($q, string $d) => $q->orderBy('ts', $d)->orderBy('id', $d),
            'coin' => fn ($q, string $d) => $q->orderBy('coin', $d)->orderBy('id', $d),
            'wallet' => fn ($q, string $d) => $q->orderBy('wallet', $d)->orderBy('id', $d),
            'quantity' => fn ($q, string $d) => $q->orderByRaw('CAST(quantity AS DECIMAL(40,20)) '.$d)->orderBy('id', $d),
            'price_usd' => fn ($q, string $d) => $q->orderBy('price_usd', $d)->orderBy('id', $d),
            'price_btc' => fn ($q, string $d) => $q->orderBy('price_btc', $d)->orderBy('id', $d),
            'id' => fn ($q, string $d) => $q->orderBy('id', $d),
        ], fn ($q) => $q->orderByDesc('ts')->orderByDesc('id'));

        return Inertia::render('Crypto/Index', [
            'transactions' => $query->paginate(ListPagination::PER_PAGE)->withQueryString(),
            'filters' => $this->filterValues($request),
            'sort' => $this->sortColumn($request),
            'direction' => $this->sortDirection($request),
            'coinEq' => $request->input('coin_eq'),
            'coins' => $this->cryptoService->catalogCoins(),
            'wallets' => $this->cryptoService->catalogWallets(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        CoinTransaction::query()->create($data);

        return redirect()
            ->route('crypto.index', ['coin_eq' => $data['coin']])
            ->with('success', 'Transacción creada.');
    }

    public function update(Request $request, CoinTransaction $crypto): RedirectResponse
    {
        $data = $this->validated($request);
        $crypto->update($data);

        return redirect()
            ->route('crypto.index', ['coin_eq' => $data['coin']])
            ->with('success', 'Transacción actualizada.');
    }

    public function destroy(CoinTransaction $crypto): RedirectResponse
    {
        $coin = $crypto->coin;
        $crypto->delete();

        return redirect()
            ->route('crypto.index', $coin ? ['coin_eq' => $coin] : [])
            ->with('success', 'Transacción eliminada.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'coin' => ['required', 'string', 'max:255'],
            'wallet' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required'],
            'ts' => ['required', 'date'],
            'price_usd' => ['nullable'],
            'price_btc' => ['nullable'],
        ]);

        $coin = strtoupper(trim($data['coin']));
        $coinOk = \App\Models\CryptoCoin::query()
            ->where('codigo', $coin)
            ->where('habilitada', true)
            ->exists();
        if (! $coinOk) {
            throw ValidationException::withMessages([
                'coin' => 'La coin no está tipificada o está deshabilitada.',
            ]);
        }

        if (! empty($data['wallet'])) {
            $walletOk = \App\Models\CryptoWallet::query()
                ->where('codigo', $data['wallet'])
                ->where('habilitada', true)
                ->exists();
            if (! $walletOk) {
                throw ValidationException::withMessages([
                    'wallet' => 'La wallet no está tipificada o está deshabilitada.',
                ]);
            }
        }

        $quantity = Money::parse($data['quantity']) ?? (is_numeric($data['quantity']) ? (string) $data['quantity'] : null);
        if ($quantity === null) {
            throw ValidationException::withMessages([
                'quantity' => 'Cantidad inválida.',
            ]);
        }

        $priceUsd = Money::parse($data['price_usd'] ?? null);
        $priceBtc = Money::parse($data['price_btc'] ?? null);

        return [
            'coin' => $coin,
            'wallet' => $data['wallet'] !== null && $data['wallet'] !== '' ? trim($data['wallet']) : null,
            'quantity' => $quantity,
            'ts' => Carbon::parse($data['ts'])->toDateTimeString(),
            'price_usd' => $priceUsd ?? '0',
            'price_btc' => $priceBtc ?? '0',
        ];
    }
}
