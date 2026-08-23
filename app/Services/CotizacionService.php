<?php

namespace App\Services;

use App\Models\Moneda;
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
