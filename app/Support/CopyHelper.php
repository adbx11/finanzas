<?php

namespace App\Support;

use Carbon\Carbon;

class CopyHelper
{
    /**
     * Si la fecha del registro copiado es hoy o anterior, usar la fecha actual (comportamiento legacy).
     */
    public static function fechaParaCopia(Carbon|string $fecha): string
    {
        $parsed = Carbon::parse($fecha);

        if ($parsed->lte(now()->startOfDay())) {
            return now()->toDateString();
        }

        return $parsed->toDateString();
    }
}
