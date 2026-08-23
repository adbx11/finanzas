<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class ListPagination
{
    public const PER_PAGE = 100;

    /**
     * Año del listado: ausente → año actual; vacío → null (todos).
     */
    public static function optionalYear(Request $request): ?int
    {
        if (! $request->has('year')) {
            return (int) now()->year;
        }

        return $request->filled('year') ? (int) $request->input('year') : null;
    }

    /**
     * Mes del listado: ausente → mes actual; vacío → null (todos).
     */
    public static function optionalMonth(Request $request): ?int
    {
        if (! $request->has('month')) {
            return (int) now()->month;
        }

        if (! $request->filled('month')) {
            return null;
        }

        $month = (int) $request->input('month');

        return ($month >= 1 && $month <= 12) ? $month : null;
    }

    public static function applyDatePeriod(Builder $query, string $column, ?int $year, ?int $month): Builder
    {
        if ($year !== null && $month !== null) {
            $desde = sprintf('%04d-%02d-01', $year, $month);
            $hasta = date('Y-m-t', strtotime($desde));

            return $query->whereBetween($column, [$desde, $hasta]);
        }

        if ($year !== null) {
            return $query->whereYear($column, $year);
        }

        if ($month !== null) {
            return $query->whereMonth($column, $month);
        }

        return $query;
    }
}
