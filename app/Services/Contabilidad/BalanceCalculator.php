<?php

namespace App\Services\Contabilidad;

use App\Models\Cuenta;
use App\Models\Moneda;
use App\Services\CotizacionService;
use App\Support\Money;
use Carbon\Carbon;

class BalanceCalculator
{
    public function __construct(
        private CuentaSaldoService $saldoService,
        private CotizacionService $cotizacionService,
    ) {}

    /**
     * @return array{desde: ?string, hasta: string, con_saldo: bool, revaluar: bool, monedas: array, cuentas: array}
     */
    public function calcular(
        ?Carbon $desde,
        Carbon $hasta,
        bool $conSaldo = true,
        bool $revaluarMonedaExtranjera = true,
    ): array {
        $monedas = Moneda::query()->orderBy('codigo')->get();
        $cuentas = [];

        $raices = Cuenta::query()
            ->with('moneda')
            ->where('nivel', 0)
            ->orderBy('codigo')
            ->get();

        foreach ($raices as $raiz) {
            $this->addCuenta($raiz, $desde, $hasta, $revaluarMonedaExtranjera, $cuentas);
        }

        usort($cuentas, fn ($a, $b) => strcmp($a['cuenta']['codigo'], $b['cuenta']['codigo']));

        if ($conSaldo) {
            $cuentas = array_values(array_filter(
                $cuentas,
                fn ($row) => ! Money::isZero((string) $row['saldos']['saldo'])
                    || ! Money::isZero((string) $row['saldos']['saldo_origen'])
            ));
        }

        return [
            'desde' => $desde?->toDateString(),
            'hasta' => $hasta->toDateString(),
            'con_saldo' => $conSaldo,
            'revaluar' => $revaluarMonedaExtranjera,
            'monedas' => $monedas->map(fn (Moneda $m) => [
                'id' => $m->id,
                'codigo' => $m->codigo,
                'simbolo' => $m->simbolo,
                'local' => (bool) $m->local,
            ])->values()->all(),
            'cuentas' => $cuentas,
        ];
    }

    private function addCuenta(
        Cuenta $cuenta,
        ?Carbon $desde,
        Carbon $hasta,
        bool $revaluar,
        array &$flat,
    ): array {
        $cuenta->loadMissing('moneda');
        $saldos = $this->saldoService->getSaldos($cuenta, $desde, $hasta);

        if ($revaluar && $cuenta->moneda && ! $cuenta->moneda->local) {
            $rate = $this->cotizacionService->getRateForDate($cuenta->moneda, $hasta);
            $saldos['saldo'] = Money::round(Money::mul($saldos['saldo_origen'], $rate, 8), 2);
        }

        $hijas = Cuenta::query()
            ->with('moneda')
            ->where('id_superior', $cuenta->id)
            ->orderBy('codigo')
            ->get();

        foreach ($hijas as $hija) {
            $child = $this->addCuenta($hija, $desde, $hasta, $revaluar, $flat);
            $saldos = $this->saldoService->addSaldos($saldos, $child['saldos']);
        }

        $row = [
            'cuenta' => [
                'id' => $cuenta->id,
                'codigo' => $cuenta->codigo,
                'descripcion' => $cuenta->descripcion,
                'nivel' => (int) $cuenta->nivel,
                'imputable' => (bool) $cuenta->imputable,
                'moneda' => $cuenta->moneda ? [
                    'id' => $cuenta->moneda->id,
                    'codigo' => $cuenta->moneda->codigo,
                    'simbolo' => $cuenta->moneda->simbolo,
                    'local' => (bool) $cuenta->moneda->local,
                ] : null,
            ],
            'saldos' => $saldos,
        ];

        $flat[] = $row;

        return $row;
    }
}
