<?php

namespace App\Http\Controllers;

use App\Models\Cuenta;
use App\Models\Moneda;
use App\Services\Contabilidad\ConciliacionService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

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

    public function asientoIntereses(Request $request): Response|RedirectResponse
    {
        return $this->renderDraftAsiento($request, 'intereses');
    }

    public function asientoAjuste(Request $request): Response|RedirectResponse
    {
        return $this->renderDraftAsiento($request, 'ajuste');
    }

    private function renderDraftAsiento(Request $request, string $tipo): Response|RedirectResponse
    {
        $data = $request->validate([
            'id_cuenta' => ['required', 'integer', 'exists:cuentas,id'],
            'fecha' => ['required', 'date'],
            'saldo_real' => ['required'],
        ]);

        $saldoReal = Money::parse($data['saldo_real']);
        if ($saldoReal === null) {
            return redirect()
                ->route('conciliacion.index', ['fecha' => $data['fecha']])
                ->with('error', 'Saldo real inválido.');
        }

        try {
            $draft = $this->conciliacionService->draftDiferencia(
                Carbon::parse($data['fecha'])->startOfDay(),
                (int) $data['id_cuenta'],
                $saldoReal,
                $tipo,
            );
        } catch (Throwable $e) {
            return redirect()
                ->route('conciliacion.index', ['fecha' => $data['fecha']])
                ->with('error', $e->getMessage());
        }

        return Inertia::render('Asientos/Form', [
            'monedas' => Moneda::query()->orderBy('codigo')->get(),
            'cuentas' => Cuenta::query()
                ->habilitadas()
                ->imputables()
                ->with('moneda')
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'descripcion', 'id_moneda']),
            'asiento' => $draft,
            'isCopy' => false,
            'isPrefill' => true,
        ]);
    }
}
