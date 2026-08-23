<?php

namespace App\Http\Controllers;

use App\Services\Contabilidad\TarjetaLiquidacionService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TarjetaController extends Controller
{
    public function __construct(
        private TarjetaLiquidacionService $tarjetaService,
    ) {}

    public function index(Request $request): Response
    {
        $default = $this->tarjetaService->defaultPeriod();
        $year = (int) $request->input('year', $default['year']);
        $month = (int) $request->input('month', $default['month']);

        if ($month < 1 || $month > 12) {
            $month = $default['month'];
        }

        $resumen = $this->tarjetaService->resumen($year, $month);

        return Inertia::render('Tarjetas/Index', [
            ...$resumen,
            'cuentasOrigen' => $this->tarjetaService->cuentasOrigenPago(),
        ]);
    }

    public function liquidar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id_cuenta' => ['required', 'integer', 'exists:cuentas,id'],
            'id_cuenta_origen' => ['required', 'integer', 'exists:cuentas,id'],
            'fecha' => ['required', 'date'],
            'importe' => ['required'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $importe = Money::parse($data['importe']);
        if ($importe === null) {
            return back()->withErrors(['importe' => 'Importe inválido.'])->withInput();
        }

        $this->tarjetaService->liquidar(
            (int) $data['id_cuenta'],
            (int) $data['id_cuenta_origen'],
            Carbon::parse($data['fecha'])->startOfDay(),
            $importe,
            (int) $data['year'],
            (int) $data['month'],
        );

        return redirect()
            ->route('tarjetas.index', [
                'year' => $data['year'],
                'month' => $data['month'],
            ])
            ->with('success', 'Se ha actualizado el pago de la tarjeta.');
    }
}
