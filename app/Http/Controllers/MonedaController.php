<?php

namespace App\Http\Controllers;

use App\Http\Concerns\AppliesColumnFilters;
use App\Http\Concerns\AppliesTableSorting;
use App\Models\Moneda;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonedaController extends Controller
{
    use AppliesColumnFilters;
    use AppliesTableSorting;

    public function index(Request $request): Response
    {
        $query = Moneda::query()->orderBy('codigo');

        $this->applyLikeFilter($query, $this->filterString($request, 'codigo'), 'codigo');
        $this->applyLikeFilter($query, $this->filterString($request, 'simbolo'), 'simbolo');

        if (($local = $this->filterString($request, 'local')) !== null) {
            $normalized = strtolower($local);
            if (in_array($normalized, ['s', 'si', 'sí', '1', 'true', 'yes'], true)) {
                $query->where('local', true);
            } elseif (in_array($normalized, ['n', 'no', '0', 'false'], true)) {
                $query->where('local', false);
            }
        }

        $this->applyTableSorting($query, $request, [
            'codigo' => fn ($q, string $d) => $q->orderBy('codigo', $d)->orderBy('id', $d),
            'simbolo' => fn ($q, string $d) => $q->orderBy('simbolo', $d)->orderBy('id', $d),
            'local' => fn ($q, string $d) => $q->orderBy('local', $d)->orderBy('id', $d),
        ], fn ($q) => $q->orderBy('codigo'));

        return Inertia::render('Monedas/Index', [
            'monedas' => $query->get(),
            'filters' => $this->filterValues($request),
            'sort' => $this->sortColumn($request),
            'direction' => $this->sortDirection($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:3', 'unique:monedas,codigo'],
            'simbolo' => ['required', 'string', 'max:24'],
            'local' => ['boolean'],
        ]);

        if (! empty($data['local'])) {
            Moneda::query()->update(['local' => false]);
        }

        Moneda::query()->create($data);

        return redirect()->route('monedas.index');
    }

    public function update(Request $request, Moneda $moneda): RedirectResponse
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:3', 'unique:monedas,codigo,'.$moneda->id],
            'simbolo' => ['required', 'string', 'max:24'],
            'local' => ['boolean'],
        ]);

        if (! empty($data['local'])) {
            Moneda::query()->where('id', '!=', $moneda->id)->update(['local' => false]);
        }

        $moneda->update($data);

        return redirect()->route('monedas.index');
    }

    public function destroy(Moneda $moneda): RedirectResponse
    {
        $moneda->delete();

        return redirect()->route('monedas.index');
    }
}
