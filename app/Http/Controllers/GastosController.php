<?php

namespace App\Http\Controllers;

use App\Services\Contabilidad\GastosService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GastosController extends Controller
{
    public function __construct(
        private GastosService $gastosService,
    ) {}

    public function index(Request $request): Response
    {
        $zoom = $request->input('zoom', 'mensual');
        $hasta = $request->filled('hasta')
            ? Carbon::parse($request->input('hasta'))->startOfDay()
            : now()->endOfYear()->startOfDay();
        $desde = $request->filled('desde')
            ? Carbon::parse($request->input('desde'))->startOfDay()
            : $hasta->copy()->startOfYear();

        $report = $this->gastosService->report($desde, $hasta, (string) $zoom);

        return Inertia::render('Informes/Gastos', [
            'report' => $report,
            'filters' => [
                'desde' => $report['desde'],
                'hasta' => $report['hasta'],
                'zoom' => $report['zoom'],
            ],
        ]);
    }
}
