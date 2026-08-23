<?php

namespace App\Http\Controllers;

use App\Http\Concerns\AppliesColumnFilters;
use App\Http\Concerns\AppliesTableSorting;
use App\Models\Cuenta;
use App\Models\Ingreso;
use App\Models\Moneda;
use App\Services\Contabilidad\AsientoGenerator;
use App\Services\CotizacionService;
use App\Support\CopyHelper;
use App\Support\ListPagination;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IngresoController extends Controller
{
    use AppliesColumnFilters;
    use AppliesTableSorting;

    public function __construct(
        private AsientoGenerator $asientoGenerator,
        private CotizacionService $cotizacionService,
    ) {}

    public function index(Request $request): Response
    {
        $year = ListPagination::optionalYear($request);
        $month = ListPagination::optionalMonth($request);

        $baseQuery = $this->filteredIngresosQuery($request, $year, $month);

        $this->applyTableSorting($baseQuery, $request, $this->ingresosSortMap(), function ($query) {
            $query->orderByDesc('ingresos.fecha')->orderByDesc('ingresos.id');
        });

        $ingresos = (clone $baseQuery)
            ->paginate(ListPagination::PER_PAGE)
            ->withQueryString();

        $total = (clone $baseQuery)
            ->selectRaw('COALESCE(SUM(importe * cotizacion), 0) as total')
            ->value('total');

        return Inertia::render('Ingresos/Index', [
            'ingresos' => $ingresos,
            'total' => Money::format((string) $total),
            'year' => $year,
            'month' => $month,
            'filters' => $this->filterValues($request),
            'sort' => $this->sortColumn($request),
            'direction' => $this->sortDirection($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $ingreso = null;

        if ($request->filled('from')) {
            $source = Ingreso::query()->findOrFail($request->integer('from'));
            $ingreso = [
                'fecha' => CopyHelper::fechaParaCopia($source->fecha),
                'id_cuenta_concepto' => $source->id_cuenta_concepto,
                'id_cuenta_destino' => $source->id_cuenta_destino,
                'id_moneda' => $source->id_moneda,
                'importe' => $source->importe,
                'cotizacion' => $source->cotizacion,
                'comentarios' => $source->comentarios,
            ];
        }

        return Inertia::render('Ingresos/Form', [
            ...$this->formData(),
            'ingreso' => $ingreso,
            'isCopy' => $request->filled('from'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $ingreso = new Ingreso($this->validated($request));
        $ingreso->save();
        $this->asientoGenerator->replaceForIngreso($ingreso);

        return redirect()->route('ingresos.index');
    }

    public function edit(Ingreso $ingreso): Response
    {
        $ingreso->load(['cuentaConcepto', 'cuentaDestino', 'moneda']);

        return Inertia::render('Ingresos/Form', [
            ...$this->formData(),
            'ingreso' => $ingreso,
        ]);
    }

    public function update(Request $request, Ingreso $ingreso): RedirectResponse
    {
        $ingreso->fill($this->validated($request));
        $ingreso->save();
        $this->asientoGenerator->replaceForIngreso($ingreso);

        return redirect()->route('ingresos.index');
    }

    public function destroy(Ingreso $ingreso): RedirectResponse
    {
        if ($ingreso->id_asiento) {
            $this->asientoGenerator->deleteAsiento($ingreso->id_asiento);
        }

        $ingreso->delete();

        return redirect()->route('ingresos.index');
    }

    public function cotizacion(Request $request)
    {
        $data = $request->validate([
            'id_moneda' => ['required', 'integer', 'exists:monedas,id'],
            'fecha' => ['required', 'date'],
        ]);

        $moneda = Moneda::query()->findOrFail($data['id_moneda']);
        $rate = $this->cotizacionService->getRateForDate($moneda, Carbon::parse($data['fecha']));

        return response()->json(['cotizacion' => $rate]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'id_cuenta_concepto' => ['required', 'integer', 'exists:cuentas,id'],
            'id_cuenta_destino' => ['required', 'integer', 'exists:cuentas,id'],
            'id_moneda' => ['required', 'integer', 'exists:monedas,id'],
            'importe' => ['required'],
            'cotizacion' => ['required'],
            'comentarios' => ['nullable', 'string', 'max:255'],
        ]);

        $data['importe'] = Money::parse($data['importe']) ?? $data['importe'];
        $data['cotizacion'] = Money::parse($data['cotizacion']) ?? $data['cotizacion'];

        return $data;
    }

    private function formData(): array
    {
        return [
            'monedas' => Moneda::query()->orderBy('codigo')->get(),
            'cuentasConcepto' => Cuenta::query()->habilitadas()->imputables()->codigoPatterns(Cuenta::FCODIGO_INGRESO_CONCEPTO)->orderBy('codigo')->get(),
            'cuentasDestino' => Cuenta::query()->habilitadas()->imputables()->codigoPatterns(Cuenta::FCODIGO_INGRESO_DESTINO)->orderBy('codigo')->get(),
        ];
    }

    private function filteredIngresosQuery(Request $request, ?int $year, ?int $month)
    {
        $query = Ingreso::query()
            ->with(['cuentaConcepto', 'cuentaDestino', 'moneda']);

        ListPagination::applyDatePeriod($query, 'fecha', $year, $month);

        $this->applyLikeFilter($query, $this->filterString($request, 'fecha'), 'fecha');
        $this->applyRelationLikeFilter($query, $this->filterString($request, 'concepto'), 'cuentaConcepto', 'descripcion');
        $this->applyRelationLikeFilter($query, $this->filterString($request, 'destino'), 'cuentaDestino', 'descripcion');
        $this->applyRelationMultiLikeFilter($query, $this->filterString($request, 'moneda'), 'moneda', ['simbolo', 'codigo']);
        $this->applyLikeFilter($query, $this->filterString($request, 'importe'), 'importe');
        $this->applyLikeFilter($query, $this->filterString($request, 'comentarios'), 'comentarios');

        return $query;
    }

    private function ingresosSortMap(): array
    {
        return [
            'fecha' => fn ($query, string $direction) => $query
                ->orderBy('ingresos.fecha', $direction)
                ->orderBy('ingresos.id', $direction),
            'concepto' => fn ($query, string $direction) => $query
                ->orderBy(
                    Cuenta::query()
                        ->select('descripcion')
                        ->whereColumn('cuentas.id', 'ingresos.id_cuenta_concepto')
                        ->limit(1),
                    $direction
                )
                ->orderBy('ingresos.id', $direction),
            'destino' => fn ($query, string $direction) => $query
                ->orderBy(
                    Cuenta::query()
                        ->select('descripcion')
                        ->whereColumn('cuentas.id', 'ingresos.id_cuenta_destino')
                        ->limit(1),
                    $direction
                )
                ->orderBy('ingresos.id', $direction),
            'moneda' => fn ($query, string $direction) => $query
                ->orderBy(
                    Moneda::query()
                        ->select('simbolo')
                        ->whereColumn('monedas.id', 'ingresos.id_moneda')
                        ->limit(1),
                    $direction
                )
                ->orderBy('ingresos.id', $direction),
            'importe' => fn ($query, string $direction) => $query
                ->orderBy('ingresos.importe', $direction)
                ->orderBy('ingresos.id', $direction),
            'comentarios' => fn ($query, string $direction) => $query
                ->orderBy('ingresos.comentarios', $direction)
                ->orderBy('ingresos.id', $direction),
        ];
    }
}
