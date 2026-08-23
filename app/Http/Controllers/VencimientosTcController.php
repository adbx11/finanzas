<?php

namespace App\Http\Controllers;

use App\Services\Contabilidad\VencimientosTcService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VencimientosTcController extends Controller
{
    public function __construct(
        private VencimientosTcService $service,
    ) {}

    public function index(Request $request): Response
    {
        $desde = $request->filled('desde')
            ? Carbon::parse($request->input('desde'))->startOfDay()
            : now()->startOfDay();
        $hasta = $request->filled('hasta')
            ? Carbon::parse($request->input('hasta'))->startOfDay()
            : $desde->copy()->addMonthsNoOverflow(12)->endOfMonth()->startOfDay();

        if ($hasta->lt($desde)) {
            $hasta = $desde->copy()->addMonthsNoOverflow(12)->endOfMonth()->startOfDay();
        }

        $report = $this->service->report($desde, $hasta);

        return Inertia::render('Informes/VencimientosTc', [
            'report' => $report,
            'filters' => [
                'desde' => $report['desde'],
                'hasta' => $report['hasta'],
            ],
        ]);
    }
}
