<?php

namespace App\Http\Controllers;

use App\Services\Contabilidad\ConciliacionService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConciliacionController extends Controller
{
    public function __construct(
        private ConciliacionService $conciliacionService,
    ) {}

    public function index(Request $request): Response
    {
        $fecha = $request->filled('fecha')
            ? Carbon::parse($request->input('fecha'))->startOfDay()
            : now()->startOfDay();

        $cuentas = $this->conciliacionService->preview($fecha);

        return Inertia::render('Conciliacion/Index', [
            'fecha' => $fecha->toDateString(),
            'cuentas' => $cuentas,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.id_cuenta' => ['required', 'integer', 'exists:cuentas,id'],
            'lineas.*.saldo_real' => ['required'],
        ]);

        $fecha = Carbon::parse($data['fecha'])->startOfDay();

        $lineas = [];
        foreach ($data['lineas'] as $i => $linea) {
            $saldoReal = Money::parse($linea['saldo_real']);
            if ($saldoReal === null) {
                return back()->withErrors([
                    "lineas.{$i}.saldo_real" => 'Saldo real inválido.',
                ])->withInput();
            }
            $lineas[] = [
                'id_cuenta' => (int) $linea['id_cuenta'],
                'saldo_real' => $saldoReal,
            ];
        }

        $asiento = $this->conciliacionService->save($fecha, $lineas);

        if ($asiento) {
            return redirect()
                ->route('conciliacion.index', ['fecha' => $fecha->toDateString()])
                ->with('success', 'Se ha registrado el asiento de conciliación #'.$asiento->id.'.');
        }

        return redirect()
            ->route('conciliacion.index', ['fecha' => $fecha->toDateString()])
            ->with('success', 'No había diferencias: no se generó asiento.');
    }
}
