<?php

namespace App\Http\Controllers;

use App\Models\Cuenta;
use App\Services\Contabilidad\EvolucionCuentaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EvolucionCuentaController extends Controller
{
    public function __construct(
        private EvolucionCuentaService $service,
    ) {}

    public function index(Request $request): Response
    {
        $zoom = (string) $request->input('zoom', 'mensual');
        $hasta = $request->filled('hasta')
            ? Carbon::parse($request->input('hasta'))->startOfDay()
            : now()->startOfDay();
        $desde = $request->filled('desde')
            ? Carbon::parse($request->input('desde'))->startOfDay()
            : $this->defaultDesde($hasta, $zoom);

        $idCuenta = $request->integer('id_cuenta') ?: null;
        $report = null;

        if ($idCuenta) {
            $cuenta = Cuenta::query()->findOrFail($idCuenta);
            $report = $this->service->report($cuenta, $desde, $hasta, $zoom);
        }

        return Inertia::render('Informes/EvolucionCuenta', [
            'report' => $report,
            'cuentas' => Cuenta::query()
                ->habilitadas()
                ->imputables()
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'descripcion', 'id_moneda']),
            'filters' => [
                'id_cuenta' => $idCuenta,
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
                'zoom' => in_array($zoom, ['mensual', 'anual', 'fecha'], true) ? $zoom : 'mensual',
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
