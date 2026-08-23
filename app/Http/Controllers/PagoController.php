<?php

namespace App\Http\Controllers;

use App\Http\Concerns\AppliesColumnFilters;
use App\Http\Concerns\AppliesTableSorting;
use App\Models\Cuenta;
use App\Models\Moneda;
use App\Models\Pago;
use App\Services\Contabilidad\AsientoGenerator;
use App\Services\Contabilidad\CuotaGenerator;
use App\Services\CotizacionService;
use App\Support\CopyHelper;
use App\Support\ListPagination;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PagoController extends Controller
{
    use AppliesColumnFilters;
    use AppliesTableSorting;

    public function __construct(
        private AsientoGenerator $asientoGenerator,
        private CuotaGenerator $cuotaGenerator,
        private CotizacionService $cotizacionService,
    ) {}

    public function index(Request $request): Response
    {
        $year = ListPagination::optionalYear($request);
        $month = ListPagination::optionalMonth($request);

        $baseQuery = $this->filteredPagosQuery($request, $year, $month);

        $this->applyTableSorting($baseQuery, $request, $this->pagosSortMap(), function ($query) {
            $query->orderByDesc('pagos.fecha')->orderByDesc('pagos.id');
        });

        $pagos = (clone $baseQuery)
            ->paginate(ListPagination::PER_PAGE)
            ->withQueryString();

        $total = (clone $baseQuery)
            ->selectRaw('COALESCE(SUM(importe * cotizacion), 0) as total')
            ->value('total');

        return Inertia::render('Pagos/Index', [
            'pagos' => $pagos,
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
        $pago = null;

        if ($request->filled('from')) {
            $source = Pago::query()->findOrFail($request->integer('from'));
            $pago = [
                'fecha' => CopyHelper::fechaParaCopia($source->fecha),
                'id_cuenta_concepto' => $source->id_cuenta_concepto,
                'id_cuenta_origen' => $source->id_cuenta_origen,
                'id_moneda' => $source->id_moneda,
                'importe' => $source->importe,
                'cotizacion' => $source->cotizacion,
                'comentarios' => $source->comentarios,
                'cuotas' => $source->cuotas ?? 0,
            ];
        }

        return Inertia::render('Pagos/Form', [
            ...$this->formData(),
            'pago' => $pago,
            'isCopy' => $request->filled('from'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $pago = new Pago($this->validated($request));
        $pago->cuota = null;
        $pago->id_origen = null;
        $pago->save();

        $this->asientoGenerator->replaceForPago($pago);
        $this->cuotaGenerator->regenerate($pago);

        return redirect()->route('pagos.index');
    }

    public function edit(Pago $pago): Response
    {
        $pago->load(['cuentaConcepto', 'cuentaOrigen', 'moneda']);

        return Inertia::render('Pagos/Form', [
            ...$this->formData(),
            'pago' => $pago,
            'isCuota' => $pago->id_origen !== null,
        ]);
    }

    public function update(Request $request, Pago $pago): RedirectResponse
    {
        $pago->fill($this->validated($request, $pago->id_origen !== null));

        if ($pago->id_origen !== null) {
            $pago->save();
            $this->asientoGenerator->replaceForPago($pago);

            return redirect()->route('pagos.index');
        }

        $pago->cuota = null;
        $pago->save();
        $this->asientoGenerator->replaceForPago($pago);
        $this->cuotaGenerator->regenerate($pago);

        return redirect()->route('pagos.index');
    }

    public function destroy(Pago $pago): RedirectResponse
    {
        $this->cuotaGenerator->deletePago($pago);

        return redirect()->route('pagos.index');
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

    private function validated(Request $request, bool $isCuota = false): array
    {
        $rules = [
            'fecha' => ['required', 'date'],
            'id_cuenta_concepto' => ['required', 'integer', 'exists:cuentas,id'],
            'id_cuenta_origen' => ['required', 'integer', 'exists:cuentas,id'],
            'id_moneda' => ['required', 'integer', 'exists:monedas,id'],
            'importe' => ['required'],
            'cotizacion' => ['required'],
            'comentarios' => ['nullable', 'string', 'max:255'],
        ];

        if (! $isCuota) {
            $rules['cuotas'] = ['nullable', 'integer', 'min:0', 'max:48'];
        }

        $data = $request->validate($rules);

        $data['importe'] = Money::parse($data['importe']) ?? $data['importe'];
        $data['cotizacion'] = Money::parse($data['cotizacion']) ?? $data['cotizacion'];
        $data['cuotas'] = $isCuota ? null : (int) ($data['cuotas'] ?? 0);

        return $data;
    }

    private function formData(): array
    {
        return [
            'monedas' => Moneda::query()->orderBy('codigo')->get(),
            'cuentasConcepto' => Cuenta::query()->habilitadas()->imputables()->codigoPatterns(Cuenta::FCODIGO_PAGO_CONCEPTO)->orderBy('codigo')->get(),
            'cuentasOrigen' => Cuenta::query()->habilitadas()->imputables()->codigoPatterns(Cuenta::FCODIGO_PAGO_ORIGEN)->orderBy('codigo')->get(),
        ];
    }

    private function filteredPagosQuery(Request $request, ?int $year, ?int $month)
    {
        $query = Pago::query()
            ->with(['cuentaConcepto', 'cuentaOrigen', 'moneda']);

        ListPagination::applyDatePeriod($query, 'fecha', $year, $month);

        $this->applyLikeFilter($query, $this->filterString($request, 'fecha'), 'fecha');
        $this->applyRelationLikeFilter($query, $this->filterString($request, 'concepto'), 'cuentaConcepto', 'descripcion');
        $this->applyRelationLikeFilter($query, $this->filterString($request, 'origen'), 'cuentaOrigen', 'descripcion');
        $this->applyRelationMultiLikeFilter($query, $this->filterString($request, 'moneda'), 'moneda', ['simbolo', 'codigo']);
        $this->applyLikeFilter($query, $this->filterString($request, 'importe'), 'importe');
        $this->applyLikeFilter($query, $this->filterString($request, 'comentarios'), 'comentarios');

        return $query;
    }

    private function pagosSortMap(): array
    {
        return [
            'fecha' => fn ($query, string $direction) => $query
                ->orderBy('pagos.fecha', $direction)
                ->orderBy('pagos.id', $direction),
            'concepto' => fn ($query, string $direction) => $query
                ->orderBy(
                    Cuenta::query()
                        ->select('descripcion')
                        ->whereColumn('cuentas.id', 'pagos.id_cuenta_concepto')
                        ->limit(1),
                    $direction
                )
                ->orderBy('pagos.id', $direction),
            'origen' => fn ($query, string $direction) => $query
                ->orderBy(
                    Cuenta::query()
                        ->select('descripcion')
                        ->whereColumn('cuentas.id', 'pagos.id_cuenta_origen')
                        ->limit(1),
                    $direction
                )
                ->orderBy('pagos.id', $direction),
            'moneda' => fn ($query, string $direction) => $query
                ->orderBy(
                    Moneda::query()
                        ->select('simbolo')
                        ->whereColumn('monedas.id', 'pagos.id_moneda')
                        ->limit(1),
                    $direction
                )
                ->orderBy('pagos.id', $direction),
            'importe' => fn ($query, string $direction) => $query
                ->orderBy('pagos.importe', $direction)
                ->orderBy('pagos.id', $direction),
            'comentarios' => fn ($query, string $direction) => $query
                ->orderBy('pagos.comentarios', $direction)
                ->orderBy('pagos.id', $direction),
        ];
    }
}
