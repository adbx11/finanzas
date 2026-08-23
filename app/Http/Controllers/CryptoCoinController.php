<?php

namespace App\Http\Controllers;

use App\Http\Concerns\AppliesColumnFilters;
use App\Http\Concerns\AppliesTableSorting;
use App\Models\CryptoCoin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CryptoCoinController extends Controller
{
    use AppliesColumnFilters;
    use AppliesTableSorting;

    public function index(Request $request): Response
    {
        $query = CryptoCoin::query();

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
            'habilitada' => fn ($q, string $d) => $q->orderBy('habilitada', $d)->orderBy('id', $d),
        ], fn ($q) => $q->orderBy('codigo'));

        return Inertia::render('CryptoCoins/Index', [
            'coins' => $query->get(),
            'filters' => $this->filterValues($request),
            'sort' => $this->sortColumn($request),
            'direction' => $this->sortDirection($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        CryptoCoin::query()->create($data);

        return redirect()->route('crypto-coins.index')->with('success', 'Coin creada.');
    }

    public function update(Request $request, CryptoCoin $cryptoCoin): RedirectResponse
    {
        $cryptoCoin->update($this->validated($request, $cryptoCoin));

        return redirect()->route('crypto-coins.index')->with('success', 'Coin actualizada.');
    }

    public function destroy(CryptoCoin $cryptoCoin): RedirectResponse
    {
        $cryptoCoin->delete();

        return redirect()->route('crypto-coins.index')->with('success', 'Coin eliminada.');
    }

    private function validated(Request $request, ?CryptoCoin $coin = null): array
    {
        $id = $coin?->id;
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:32', 'unique:crypto_coins,codigo,'.$id],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'habilitada' => ['boolean'],
        ]);

        $data['codigo'] = strtoupper(trim($data['codigo']));
        $data['habilitada'] = (bool) ($data['habilitada'] ?? true);

        return $data;
    }
}
