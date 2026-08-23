<?php

namespace App\Http\Controllers;

use App\Http\Concerns\AppliesColumnFilters;
use App\Http\Concerns\AppliesTableSorting;
use App\Models\CryptoCoin;
use App\Models\CryptoWallet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CryptoWalletController extends Controller
{
    use AppliesColumnFilters;
    use AppliesTableSorting;

    public function index(Request $request): Response
    {
        $query = CryptoWallet::query()->with('coin');

        $this->applyLikeFilter($query, $this->filterString($request, 'codigo'), 'codigo');
        $this->applyLikeFilter($query, $this->filterString($request, 'descripcion'), 'descripcion');

        if (($hab = $this->filterString($request, 'habilitada')) !== null) {
            $n = strtolower($hab);
            if (in_array($n, ['s', 'si', 'sí', '1', 'true', 'yes'], true)) {
                $query->where('habilitada', true);
            } elseif (in_array($n, ['n', 'no', '0', 'false'], true)) {
                $query->where('habilitada', false);
            }
        }

        $this->applyTableSorting($query, $request, [
            'codigo' => fn ($q, string $d) => $q->orderBy('codigo', $d)->orderBy('id', $d),
            'descripcion' => fn ($q, string $d) => $q->orderBy('descripcion', $d)->orderBy('id', $d),
            'coin' => fn ($q, string $d) => $q
                ->orderBy(
                    CryptoCoin::query()
                        ->select('codigo')
                        ->whereColumn('crypto_coins.id', 'crypto_wallets.id_coin')
                        ->limit(1),
                    $d
                )
                ->orderBy('id', $d),
            'habilitada' => fn ($q, string $d) => $q->orderBy('habilitada', $d)->orderBy('id', $d),
        ], fn ($q) => $q->orderBy('codigo'));

        return Inertia::render('CryptoWallets/Index', [
            'wallets' => $query->get(),
            'coins' => CryptoCoin::query()->orderBy('codigo')->get(['id', 'codigo', 'descripcion']),
            'filters' => $this->filterValues($request),
            'sort' => $this->sortColumn($request),
            'direction' => $this->sortDirection($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        CryptoWallet::query()->create($this->validated($request));

        return redirect()->route('crypto-wallets.index')->with('success', 'Wallet creada.');
    }

    public function update(Request $request, CryptoWallet $cryptoWallet): RedirectResponse
    {
        $cryptoWallet->update($this->validated($request, $cryptoWallet));

        return redirect()->route('crypto-wallets.index')->with('success', 'Wallet actualizada.');
    }

    public function destroy(CryptoWallet $cryptoWallet): RedirectResponse
    {
        $cryptoWallet->delete();

        return redirect()->route('crypto-wallets.index')->with('success', 'Wallet eliminada.');
    }

    private function validated(Request $request, ?CryptoWallet $wallet = null): array
    {
        $id = $wallet?->id;
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:255', 'unique:crypto_wallets,codigo,'.$id],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'id_coin' => ['nullable', 'integer', 'exists:crypto_coins,id'],
            'habilitada' => ['boolean'],
        ]);

        $data['codigo'] = trim($data['codigo']);
        $data['id_coin'] = $data['id_coin'] ?: null;
        $data['habilitada'] = (bool) ($data['habilitada'] ?? true);

        return $data;
    }
}
