<?php

namespace App\Http\Controllers;

use App\Models\Cuenta;
use App\Services\Contabilidad\MayorService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MayorController extends Controller
{
    public function __construct(
        private MayorService $mayorService,
    ) {}

    public function index(Request $request): Response
    {
        $desde = $request->filled('desde')
            ? Carbon::parse($request->input('desde'))
            : now()->startOfMonth();
        $hasta = $request->filled('hasta')
            ? Carbon::parse($request->input('hasta'))
            : now();

        $idCuenta = $request->integer('id_cuenta') ?: null;
        $mayor = null;

        if ($idCuenta) {
            $cuenta = Cuenta::query()->findOrFail($idCuenta);
            $mayor = $this->mayorService->generar($cuenta, $desde, $hasta);
        }

        return Inertia::render('Informes/Mayor', [
            'mayor' => $mayor,
            'cuentas' => Cuenta::query()
                ->habilitadas()
                ->imputables()
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'descripcion', 'id_moneda']),
            'filters' => [
                'id_cuenta' => $idCuenta,
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
            ],
        ]);
    }
}
