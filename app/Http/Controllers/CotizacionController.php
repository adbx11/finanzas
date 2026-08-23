<?php

namespace App\Http\Controllers;

use App\Jobs\FetchCotizacionesJob;
use App\Models\Cotizacion;
use App\Models\Moneda;
use App\Services\ConfiguracionService;
use App\Services\CotizacionFetcher;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CotizacionController extends Controller
{
    /** @var list<string> */
    private const CODIGOS = ['USD', 'EUR', 'BTC'];

    public function __construct(
        private CotizacionFetcher $fetcher,
        private ConfiguracionService $config,
    ) {}

    public function index(Request $request): Response
    {
        $fecha = $request->filled('fecha')
            ? Carbon::parse($request->input('fecha'))->toDateString()
            : now()->toDateString();

        $monedas = Moneda::query()
            ->whereIn('codigo', self::CODIGOS)
            ->orderByRaw("FIELD(codigo, 'USD', 'EUR', 'BTC')")
            ->get();

        $rates = [];
        foreach ($monedas as $moneda) {
            $row = Cotizacion::query()
                ->where('id_moneda', $moneda->id)
                ->whereDate('fecha', $fecha)
                ->first();

            $rates[$moneda->codigo] = [
                'id_moneda' => $moneda->id,
                'codigo' => $moneda->codigo,
                'simbolo' => $moneda->simbolo,
                'compra' => $row ? (string) $row->compra : '',
                'venta' => $row ? (string) $row->venta : '',
            ];
        }

        foreach (self::CODIGOS as $codigo) {
            if (! isset($rates[$codigo])) {
                $rates[$codigo] = [
                    'id_moneda' => null,
                    'codigo' => $codigo,
                    'simbolo' => $codigo,
                    'compra' => '',
                    'venta' => '',
                ];
            }
        }

        $recientes = Cotizacion::query()
            ->with('moneda')
            ->whereIn('id_moneda', $monedas->pluck('id'))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (Cotizacion $c) => [
                'id' => $c->id,
                'fecha' => optional($c->fecha)->toDateString(),
                'codigo' => $c->moneda?->codigo,
                'compra' => (string) $c->compra,
                'venta' => (string) $c->venta,
            ]);

        return Inertia::render('Cotizaciones/Index', [
            'fecha' => $fecha,
            'rates' => array_values($rates),
            'recientes' => $recientes,
            'btcConfig' => [
                'btcusd' => $this->config->get('cotizacion.btcusd', '0'),
                'btcars' => $this->config->get('cotizacion.btcars', '0'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'rates' => ['required', 'array'],
            'rates.*.codigo' => ['required', 'string', 'max:3'],
            'rates.*.compra' => ['nullable', 'string'],
            'rates.*.venta' => ['nullable', 'string'],
        ]);

        $fecha = Carbon::parse($data['fecha'])->toDateString();
        $saved = 0;

        foreach ($data['rates'] as $rate) {
            $codigo = strtoupper($rate['codigo']);
            if (! in_array($codigo, self::CODIGOS, true)) {
                continue;
            }

            $compra = Money::parse($rate['compra'] ?? null);
            $venta = Money::parse($rate['venta'] ?? null);
            if ($compra === null || $venta === null || bccomp($compra, '0', 10) <= 0 || bccomp($venta, '0', 10) <= 0) {
                continue;
            }

            $moneda = Moneda::query()->where('codigo', $codigo)->first();
            if (! $moneda) {
                continue;
            }

            $row = Cotizacion::query()
                ->where('id_moneda', $moneda->id)
                ->whereDate('fecha', $fecha)
                ->first();

            if ($row) {
                $row->update(['compra' => $compra, 'venta' => $venta]);
            } else {
                Cotizacion::query()->create([
                    'id_moneda' => $moneda->id,
                    'fecha' => $fecha,
                    'compra' => $compra,
                    'venta' => $venta,
                ]);
            }
            $saved++;
        }

        return redirect()
            ->route('cotizaciones.index', ['fecha' => $fecha])
            ->with('success', $saved > 0 ? "Se guardaron {$saved} cotización(es)." : 'No se guardó ninguna cotización válida.');
    }

    public function fetch(Request $request): RedirectResponse
    {
        $sync = $request->boolean('sync', true);

        if (! $sync) {
            FetchCotizacionesJob::dispatch(force: true);

            return redirect()
                ->route('cotizaciones.index')
                ->with('success', 'Job de cotizaciones encolado.');
        }

        try {
            $result = $this->fetcher->fetch();
            $msg = sprintf(
                'Cotizaciones actualizadas. USD compra %s / venta %s. BTC/USD %s.',
                $result['fiat'][0]['compra'] ?? '—',
                $result['fiat'][0]['venta'] ?? '—',
                $result['btc']['btcusd'] ?? '—'
            );

            return redirect()
                ->route('cotizaciones.index', ['fecha' => now()->toDateString()])
                ->with('success', $msg);
        } catch (Throwable $e) {
            Log::error('Fetch cotizaciones falló', ['error' => $e->getMessage()]);

            return redirect()
                ->route('cotizaciones.index')
                ->with('error', 'Error al obtener cotizaciones: '.$e->getMessage());
        }
    }
}
