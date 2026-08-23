<?php

namespace App\Http\Controllers;

use App\Http\Concerns\AppliesColumnFilters;
use App\Http\Concerns\AppliesTableSorting;
use App\Models\Cuenta;
use App\Models\Moneda;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CuentaController extends Controller
{
    use AppliesColumnFilters;
    use AppliesTableSorting;

    public function index(Request $request): Response
    {
        $query = Cuenta::query()
            ->with(['moneda', 'superior']);

        $this->applyLikeFilter($query, $this->filterString($request, 'codigo'), 'codigo');
        $this->applyLikeFilter($query, $this->filterString($request, 'descripcion'), 'descripcion');
        $this->applyRelationMultiLikeFilter($query, $this->filterString($request, 'moneda'), 'moneda', ['simbolo', 'codigo']);
        $this->applyLikeFilter($query, $this->filterString($request, 'clase'), 'clase');

        if (($imputable = $this->filterString($request, 'imputable')) !== null) {
            $normalized = strtolower($imputable);
            if (in_array($normalized, ['s', 'si', 'sí', '1', 'true', 'yes'], true)) {
                $query->where('imputable', true);
            } elseif (in_array($normalized, ['n', 'no', '0', 'false'], true)) {
                $query->where('imputable', false);
            }
        }

        $this->applyTableSorting($query, $request, [
            'codigo' => fn ($q, string $d) => $q->orderBy('codigo', $d)->orderBy('id', $d),
            'descripcion' => fn ($q, string $d) => $q->orderBy('descripcion', $d)->orderBy('id', $d),
            'moneda' => fn ($q, string $d) => $q
                ->orderBy(
                    Moneda::query()
                        ->select('simbolo')
                        ->whereColumn('monedas.id', 'cuentas.id_moneda')
                        ->limit(1),
                    $d
                )
                ->orderBy('id', $d),
            'clase' => fn ($q, string $d) => $q->orderBy('clase', $d)->orderBy('id', $d),
            'imputable' => fn ($q, string $d) => $q->orderBy('imputable', $d)->orderBy('id', $d),
        ], fn ($q) => $q->orderBy('codigo'));

        return Inertia::render('Cuentas/Index', [
            'cuentas' => $query->paginate(ListPagination::PER_PAGE)->withQueryString(),
            'monedas' => Moneda::query()->orderBy('codigo')->get(),
            'cuentasSuperiores' => Cuenta::query()->orderBy('codigo')->get(['id', 'codigo', 'descripcion']),
            'filters' => $this->filterValues($request),
            'sort' => $this->sortColumn($request),
            'direction' => $this->sortDirection($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        Cuenta::query()->create($data);

        return redirect()->route('cuentas.index');
    }

    public function update(Request $request, Cuenta $cuenta): RedirectResponse
    {
        $cuenta->update($this->validated($request, $cuenta));

        return redirect()->route('cuentas.index');
    }

    public function destroy(Cuenta $cuenta): RedirectResponse
    {
        $cuenta->delete();

        return redirect()->route('cuentas.index');
    }

    public function options(Request $request)
    {
        $query = Cuenta::query()
            ->habilitadas()
            ->with('moneda')
            ->orderBy('codigo');

        if ($request->boolean('imputable')) {
            $query->imputables();
        }

        if ($request->filled('fcodigo')) {
            $query->codigoPatterns($request->string('fcodigo')->toString());
        }

        if ($request->filled('clase')) {
            $query->clase($request->string('clase'));
        }

        return $query->get()->map(fn (Cuenta $c) => [
            'id' => $c->id,
            'label' => $c->label,
            'codigo' => $c->codigo,
            'descripcion' => $c->descripcion,
            'moneda' => $c->moneda,
        ]);
    }

    private function validated(Request $request, ?Cuenta $cuenta = null): array
    {
        $id = $cuenta?->id;

        return $request->validate([
            'codigo' => ['required', 'string', 'max:32', 'unique:cuentas,codigo,'.$id],
            'descripcion' => ['required', 'string', 'max:255'],
            'id_superior' => ['nullable', 'integer', 'exists:cuentas,id'],
            'id_moneda' => ['nullable', 'integer', 'exists:monedas,id'],
            'tipo_estado' => ['required', 'string', 'max:16'],
            'tipo_cuenta' => ['nullable', 'string', 'max:16'],
            'clase' => ['nullable', 'string', 'max:24'],
            'imputable' => ['boolean'],
            'habilitada' => ['boolean'],
        ]);
    }
}
