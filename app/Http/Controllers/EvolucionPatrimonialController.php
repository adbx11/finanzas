<?php

namespace App\Http\Controllers;

use App\Services\Contabilidad\EvolucionPatrimonialService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EvolucionPatrimonialController extends Controller
{
    public function __construct(
        private EvolucionPatrimonialService $service,
    ) {}

    public function index(Request $request): Response
    {
        $zoom = (string) $request->input('zoom', 'mensual');
        $moneda = strtoupper((string) $request->input('moneda', 'USD'));
        if (! in_array($moneda, ['ARS', 'USD', 'EUR', 'BTC'], true)) {
            $moneda = 'USD';
        }
        $hasta = $request->filled('hasta')
            ? Carbon::parse($request->input('hasta'))->startOfDay()
            : now()->startOfDay();
        $desde = $request->filled('desde')
            ? Carbon::parse($request->input('desde'))->startOfDay()
            : $this->defaultDesde($hasta, $zoom);

        $report = $this->service->report($desde, $hasta, $zoom);

        return Inertia::render('Informes/EvolucionPatrimonial', [
            'report' => $report,
            'filters' => [
                'desde' => $report['desde'],
                'hasta' => $report['hasta'],
                'zoom' => $report['zoom'],
                'moneda' => $moneda,
            ],
            'monedas' => [
                ['codigo' => 'USD', 'simbolo' => 'U$D', 'label' => 'Dólar (USD)'],
                ['codigo' => 'ARS', 'simbolo' => '$', 'label' => 'Pesos (ARS)'],
                ['codigo' => 'EUR', 'simbolo' => '€', 'label' => 'Euro (EUR)'],
                ['codigo' => 'BTC', 'simbolo' => '₿', 'label' => 'Bitcoin (BTC)'],
            ],
        ]);
    }

    private function defaultDesde(Carbon $hasta, string $zoom): Carbon
    {
        return match ($zoom) {
            'anual' => $hasta->copy()->subYears(5)->startOfYear(),
            'fecha' => $hasta->copy()->subMonths(3),
            default => $hasta->copy()->subMonths(12)->startOfMonth(),
        };
    }
}
