<?php

namespace App\Http\Controllers;

use App\Services\Contabilidad\InteresesService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InteresesController extends Controller
{
    public function __construct(
        private InteresesService $interesesService,
    ) {}

    public function index(Request $request): Response
    {
        $year = (int) ($request->input('year') ?: now()->year);
        $desde = $request->filled('desde')
            ? Carbon::parse($request->input('desde'))->startOfDay()
            : Carbon::create($year, 1, 1)->startOfDay();
        $hasta = $request->filled('hasta')
            ? Carbon::parse($request->input('hasta'))->startOfDay()
            : Carbon::create($year + 1, 12, 31)->startOfDay();

        $cuentasArs = $this->parseCuentas($request->input('cuentas_ars'));
        $cuentasUsd = $this->parseCuentas($request->input('cuentas_usd'));

        $report = $this->interesesService->report($desde, $hasta, $cuentasArs, $cuentasUsd);

        return Inertia::render('Informes/Intereses', [
            'report' => $report,
            'filters' => [
                'desde' => $report['desde'],
                'hasta' => $report['hasta'],
                'cuentas_ars' => implode(',', $report['cuentas_ars']),
                'cuentas_usd' => implode(',', $report['cuentas_usd']),
            ],
        ]);
    }

    /**
     * @return list<string>|null
     */
    private function parseCuentas(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $list = array_values(array_filter(array_map('trim', explode(',', (string) $value))));

        return $list === [] ? null : $list;
    }
}
