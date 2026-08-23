<?php

namespace App\Http\Controllers;

use App\Http\Concerns\AppliesColumnFilters;
use App\Http\Concerns\AppliesTableSorting;
use App\Models\Asiento;
use App\Models\AsientoItem;
use App\Models\Cuenta;
use App\Models\Moneda;
use App\Services\Contabilidad\AsientoGenerator;
use App\Services\CotizacionService;
use App\Support\CopyHelper;
use App\Support\ListPagination;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AsientoController extends Controller
{
    use AppliesColumnFilters;
    use AppliesTableSorting;

    public function __construct(
        private AsientoGenerator $asientoGenerator,
        private CotizacionService $cotizacionService,
    ) {}

    public function index(Request $request): Response
    {
        $desde = $request->exists('desde')
            ? ($request->filled('desde') ? Carbon::parse($request->input('desde'))->startOfDay() : null)
            : now()->subMonths(6)->startOfDay();
        $hasta = $request->exists('hasta')
            ? ($request->filled('hasta') ? Carbon::parse($request->input('hasta'))->endOfDay() : null)
            : null;

        $query = Asiento::query()
            ->withSum('items as total_debe', 'debe');

        if ($desde !== null) {
            $query->whereDate('fecha', '>=', $desde->toDateString());
        }
        if ($hasta !== null) {
            $query->whereDate('fecha', '<=', $hasta->toDateString());
        }

        $this->applyLikeFilter($query, $this->filterString($request, 'descripcion'), 'descripcion');
        $this->applyLikeFilter($query, $this->filterString($request, 'fecha'), 'fecha');
        $this->applyLikeFilter($query, $this->filterString($request, 'id'), 'id');

        if (($importe = $this->filterString($request, 'importe')) !== null) {
            $query->havingRaw('CAST(COALESCE(total_debe, 0) AS CHAR) LIKE ?', ['%'.$importe.'%']);
        }

        $this->applyTableSorting($query, $request, [
            'fecha' => fn ($q, string $d) => $q->orderBy('asientos.fecha', $d)->orderBy('asientos.id', $d),
            'descripcion' => fn ($q, string $d) => $q->orderBy('asientos.descripcion', $d)->orderBy('asientos.id', $d),
            'importe' => fn ($q, string $d) => $q->orderBy('total_debe', $d)->orderBy('asientos.id', $d),
            'id' => fn ($q, string $d) => $q->orderBy('asientos.id', $d),
        ], fn ($q) => $q->orderByDesc('asientos.fecha')->orderByDesc('asientos.id'));

        return Inertia::render('Asientos/Index', [
            'asientos' => $query->paginate(ListPagination::PER_PAGE)->withQueryString(),
            'desde' => $desde?->toDateString(),
            'hasta' => $hasta?->toDateString(),
            'filters' => $this->filterValues($request),
            'sort' => $this->sortColumn($request),
            'direction' => $this->sortDirection($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $asiento = null;

        if ($request->filled('from')) {
            $source = Asiento::query()->with(['items.cuenta', 'items.moneda'])->findOrFail($request->integer('from'));
            $asiento = [
                'fecha' => CopyHelper::fechaParaCopia($source->fecha),
                'descripcion' => $source->descripcion,
                'items' => $source->items->map(fn (AsientoItem $item) => [
                    'id_cuenta' => $item->id_cuenta,
                    'id_moneda' => $item->id_moneda,
                    'unidades' => $item->unidades,
                    'debe_origen' => $item->debe_origen,
                    'haber_origen' => $item->haber_origen,
                    'cotizacion' => $item->cotizacion,
                ])->values()->all(),
            ];
        }

        return Inertia::render('Asientos/Form', [
            ...$this->formData(),
            'asiento' => $asiento,
            'isCopy' => $request->filled('from'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $asiento = new Asiento([
                'fecha' => $data['fecha'],
                'descripcion' => $data['descripcion'],
                'ejercicio' => (int) Carbon::parse($data['fecha'])->format('Y'),
                'confirmado' => true,
                'fecha_creacion' => now(),
                'fecha_confirmacion' => now(),
            ]);
            $asiento->save();
            $this->saveItems($asiento, $data['items']);
        });

        return redirect()->route('asientos.index');
    }

    public function edit(Asiento $asiento): Response
    {
        $asiento->load(['items.cuenta', 'items.moneda']);

        return Inertia::render('Asientos/Form', [
            ...$this->formData(),
            'asiento' => $asiento,
        ]);
    }

    public function update(Request $request, Asiento $asiento): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($asiento, $data) {
            $asiento->fill([
                'fecha' => $data['fecha'],
                'descripcion' => $data['descripcion'],
                'ejercicio' => (int) Carbon::parse($data['fecha'])->format('Y'),
                'confirmado' => true,
                'fecha_confirmacion' => now(),
            ]);
            $asiento->save();

            AsientoItem::query()->where('id_asiento', $asiento->id)->delete();
            $this->saveItems($asiento, $data['items']);
        });

        return redirect()->route('asientos.index');
    }

    public function destroy(Asiento $asiento): RedirectResponse
    {
        $this->asientoGenerator->deleteAsientoCascade($asiento->id);

        return redirect()->route('asientos.index');
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
            'descripcion' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:2'],
            'items.*.id_cuenta' => ['required', 'integer', 'exists:cuentas,id'],
            'items.*.id_moneda' => ['required', 'integer', 'exists:monedas,id'],
            'items.*.unidades' => ['nullable', 'integer'],
            'items.*.debe_origen' => ['nullable'],
            'items.*.haber_origen' => ['nullable'],
            'items.*.cotizacion' => ['required'],
        ]);

        $totalDebe = '0';
        $totalHaber = '0';

        foreach ($data['items'] as $i => $item) {
            $debe = Money::parse($item['debe_origen'] ?? '0') ?? '0';
            $haber = Money::parse($item['haber_origen'] ?? '0') ?? '0';
            $cotizacion = Money::parse($item['cotizacion']) ?? '1';

            if (bccomp($debe, '0', 8) < 0 || bccomp($haber, '0', 8) < 0) {
                throw ValidationException::withMessages([
                    "items.{$i}.debe_origen" => 'Debe y haber no pueden ser negativos.',
                ]);
            }

            $data['items'][$i]['debe_origen'] = $debe;
            $data['items'][$i]['haber_origen'] = $haber;
            $data['items'][$i]['cotizacion'] = $cotizacion;
            $data['items'][$i]['unidades'] = $item['unidades'] !== null && $item['unidades'] !== ''
                ? (int) $item['unidades']
                : null;

            $totalDebe = Money::add($totalDebe, Money::round(Money::mul($debe, $cotizacion, 8), 2), 2);
            $totalHaber = Money::add($totalHaber, Money::round(Money::mul($haber, $cotizacion, 8), 2), 2);
        }

        if (Money::isZero($totalDebe) && Money::isZero($totalHaber)) {
            throw ValidationException::withMessages([
                'items' => 'El asiento no puede tener totales en cero.',
            ]);
        }

        if (bccomp($totalDebe, $totalHaber, 2) !== 0) {
            throw ValidationException::withMessages([
                'items' => 'El debe y el haber deben coincidir (diferencia: '.Money::format(Money::sub($totalDebe, $totalHaber, 2)).').',
            ]);
        }

        return $data;
    }

    private function saveItems(Asiento $asiento, array $items): void
    {
        foreach ($items as $row) {
            $item = new AsientoItem([
                'id_asiento' => $asiento->id,
                'id_cuenta' => $row['id_cuenta'],
                'id_moneda' => $row['id_moneda'],
                'unidades' => $row['unidades'],
                'debe_origen' => $row['debe_origen'],
                'haber_origen' => $row['haber_origen'],
                'cotizacion' => $row['cotizacion'],
            ]);
            $this->asientoGenerator->applyLocalAmounts($item);
            $item->save();
        }
    }

    private function formData(): array
    {
        return [
            'monedas' => Moneda::query()->orderBy('codigo')->get(),
            'cuentas' => Cuenta::query()
                ->habilitadas()
                ->imputables()
                ->with('moneda')
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'descripcion', 'id_moneda']),
        ];
    }
}
