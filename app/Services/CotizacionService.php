<?php

namespace App\Services;

use App\Models\Cuenta;
use App\Models\Moneda;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CotizacionService
{
    public function getRateForDate(Moneda $moneda, Carbon $fecha): string
    {
        if ($moneda->local) {
            return '1';
        }

        $rates = $this->getRatesForDate($fecha);

        return $rates[(int) $moneda->id] ?? '1';
    }

    /**
     * Promedio ponderado histórico de la cuenta (legacy CotizacionesDAO::getCotizacionPromedio).
     * Si no hay movimientos previos, usa cotización spot de la moneda de la cuenta.
     */
    public function getAverageRateForAccount(Cuenta $cuenta, Carbon $fecha): string
    {
        $cuenta->loadMissing('moneda');

        if (! $cuenta->moneda || $cuenta->moneda->local) {
            return '1';
        }

        $row = DB::selectOne(
            'SELECT SUM(ai.debe - ai.haber) AS num, SUM(ai.debe_origen - ai.haber_origen) AS den
             FROM asientos AS a
             INNER JOIN asiento_items AS ai ON a.id = ai.id_asiento AND ai.id_cuenta = ?
             WHERE a.fecha <= ?',
            [$cuenta->id, $fecha->toDateString()]
        );

        $den = isset($row->den) ? (string) $row->den : '0';
        if (bccomp($den, '0', 10) === 0) {
            return $this->getRateForDate($cuenta->moneda, $fecha);
        }

        $num = (string) $row->num;

        return Money::round(Money::div($num, $den, 12), 10);
    }

    /**
     * Última venta ≤ fecha por moneda (una sola query).
     *
     * @return array<int, string> id_moneda => venta
     */
    public function getRatesForDate(Carbon $fecha): array
    {
        static $memo = [];
        $key = $fecha->toDateString();
        if (isset($memo[$key])) {
            return $memo[$key];
        }

        $rows = DB::select(
            'SELECT c.id_moneda, c.venta
             FROM cotizaciones AS c
             INNER JOIN (
                 SELECT id_moneda, MAX(fecha) AS max_fecha
                 FROM cotizaciones
                 WHERE fecha <= ?
                   AND venta IS NOT NULL
                 GROUP BY id_moneda
             ) AS latest
               ON latest.id_moneda = c.id_moneda
              AND latest.max_fecha = c.fecha
             WHERE c.venta IS NOT NULL',
            [$key]
        );

        $rates = [];
        foreach ($rows as $row) {
            $rates[(int) $row->id_moneda] = (string) $row->venta;
        }

        return $memo[$key] = $rates;
    }
}
