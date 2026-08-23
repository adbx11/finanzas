<?php

namespace App\Services\Contabilidad;

use App\Models\Cuenta;
use App\Models\Moneda;
use App\Models\Pago;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TarjetaLiquidacionService
{
    public const FCODIGO_TARJETAS = '2.1.01.__';

    public function __construct(
        private AsientoGenerator $asientoGenerator,
    ) {}

    /**
     * Período default legacy: si día &lt; 16 → mes siguiente; si no → mes+2.
     *
     * @return array{year: int, month: int}
     */
    public function defaultPeriod(?Carbon $now = null): array
    {
        $now ??= now();
        $day = (int) $now->day;
        $month0 = (int) $now->month - 1; // 0-based como JS getMonth()
        $year = (int) $now->year;

        $defaultYear = ($month0 >= 11 && $day > 15) ? $year + 1 : $year;
        $defaultMonth = $day < 16 ? $month0 + 1 : $month0 + 2;
        if ($defaultMonth > 12) {
            $defaultMonth = 1;
        }

        return ['year' => $defaultYear, 'month' => $defaultMonth];
    }

    public function cuentasOrigenPago()
    {
        return Cuenta::query()
            ->habilitadas()
            ->imputables()
            ->codigoPatterns(Cuenta::FCODIGO_PAGO_ORIGEN)
            ->orderBy('codigo')
            ->get();
    }

    /**
     * @return array{year: int, month: int, total: string, saldo: string, tarjetas: list<array>}
     */
    public function resumen(int $year, int $month): array
    {
        $desde = Carbon::create($year, $month, 1)->startOfMonth();
        $hasta = $desde->copy()->endOfMonth();
        $today = now()->startOfDay();

        $cuentas = Cuenta::query()
            ->habilitadas()
            ->imputables()
            ->codigoPatterns(self::FCODIGO_TARJETAS)
            ->clase('TC')
            ->orderBy('codigo')
            ->get();

        $tarjetas = [];
        $totalGlobal = '0';
        $saldoGlobal = '0';

        foreach ($cuentas as $cuenta) {
            $panel = $this->panelTarjeta($cuenta, $desde, $hasta);
            if ($panel === null) {
                continue;
            }

            $tarjetas[] = $panel;
            $totalGlobal = Money::add($totalGlobal, $panel['total'], 2);

            $fechaPago = Carbon::parse($panel['fecha'])->startOfDay();
            if ($fechaPago->gt($today)) {
                $saldoGlobal = Money::add($saldoGlobal, $panel['total'], 2);
            }
        }

        return [
            'year' => $year,
            'month' => $month,
            'total' => $totalGlobal,
            'saldo' => $saldoGlobal,
            'tarjetas' => $tarjetas,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function panelTarjeta(Cuenta $cuentaTarjeta, Carbon $desde, Carbon $hasta): ?array
    {
        $cuentaOtros = $this->cuentaOtros($cuentaTarjeta);
        $pagos = $this->pagosDelMes($cuentaTarjeta->id, $desde, $hasta);

        if ($cuentaOtros) {
            $pagos = $pagos->merge($this->pagosDelMes($cuentaOtros->id, $desde, $hasta));
        }

        $pagos = $pagos->sortBy('fecha')->values();

        $total = '0';
        foreach ($pagos as $pago) {
            $total = Money::add($total, (string) $pago->importe, 2);
        }

        if (Money::isZero($total)) {
            return null;
        }

        $ultimo = $pagos->last();
        $fecha = $ultimo?->fecha
            ? Carbon::parse($ultimo->fecha)->toDateString()
            : $desde->copy()->day(15)->toDateString();

        $idCuentaOrigen = $ultimo?->id_cuenta_origen;

        return [
            'id_cuenta' => $cuentaTarjeta->id,
            'codigo' => $cuentaTarjeta->codigo,
            'descripcion' => $cuentaTarjeta->descripcion,
            'fecha' => $fecha,
            'id_cuenta_origen' => $idCuentaOrigen,
            'total' => $total,
            'pagada' => Carbon::parse($fecha)->startOfDay()->lte(now()->startOfDay()),
            'movimientos' => $pagos->map(fn (Pago $p) => [
                'id' => $p->id,
                'comentarios' => $p->comentarios,
                'importe' => (string) $p->importe,
                'cuota_completa' => $this->esCuotaCompleta($p->comentarios),
                'id_cuenta_origen' => $p->id_cuenta_origen,
                'fecha' => $p->fecha?->toDateString(),
            ])->all(),
        ];
    }

    public function cuentaOtros(Cuenta $cuentaTarjeta): ?Cuenta
    {
        $codigo = str_replace('2.1.01', '5.1.90', $cuentaTarjeta->codigo);
        if ($codigo === $cuentaTarjeta->codigo) {
            return null;
        }

        return Cuenta::query()->where('codigo', $codigo)->first();
    }

    private function pagosDelMes(int $idCuentaConcepto, Carbon $desde, Carbon $hasta)
    {
        return Pago::query()
            ->with(['cuentaOrigen', 'cuentaConcepto'])
            ->where('id_cuenta_concepto', $idCuentaConcepto)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();
    }

    public function esCuotaCompleta(?string $comentarios): bool
    {
        if (! $comentarios || ! preg_match('/\(Cuota (\d{1,2}) de (\d{1,2})\)$/', $comentarios, $m)) {
            return false;
        }

        return (int) $m[1] === (int) $m[2];
    }

    /**
     * Liquidar tarjeta del mes (save.pago.tarjeta legacy).
     */
    public function liquidar(
        int $idCuentaTarjeta,
        int $idCuentaOrigen,
        Carbon $fechaPago,
        string $importeIngresado,
        int $year,
        int $month,
    ): void {
        $cuentaTarjeta = Cuenta::query()->findOrFail($idCuentaTarjeta);
        $cuentaOrigen = Cuenta::query()->findOrFail($idCuentaOrigen);
        $cuentaOtros = $this->cuentaOtros($cuentaTarjeta);
        $monedaLocal = Moneda::local();
        if (! $monedaLocal) {
            throw new RuntimeException('No hay moneda local configurada.');
        }

        $desde = Carbon::create($year, $month, 1)->startOfMonth();
        $hasta = $desde->copy()->endOfMonth();

        $pagos = $this->pagosDelMes($cuentaTarjeta->id, $desde, $hasta);
        $pagosOtros = $cuentaOtros
            ? $this->pagosDelMes($cuentaOtros->id, $desde, $hasta)
            : collect();

        $totalSistema = '0';
        foreach ($pagos->merge($pagosOtros) as $pago) {
            $totalSistema = Money::add($totalSistema, (string) $pago->importe, 2);
        }

        $diferencia = Money::sub(Money::round($importeIngresado, 2), $totalSistema, 2);

        DB::transaction(function () use (
            $pagos,
            $pagosOtros,
            $cuentaTarjeta,
            $cuentaOrigen,
            $cuentaOtros,
            $monedaLocal,
            $fechaPago,
            $diferencia,
        ) {
            $otros = $pagosOtros->first();

            if ($otros === null && ! Money::isZero($diferencia)) {
                if (! $cuentaOtros) {
                    throw ValidationException::withMessages([
                        'importe' => 'No existe la cuenta de otros conceptos (5.1.90) para esta tarjeta.',
                    ]);
                }

                $otros = new Pago([
                    'comentarios' => $cuentaTarjeta->descripcion.' - Otros conceptos',
                    'cotizacion' => '1',
                    'id_cuenta_concepto' => $cuentaOtros->id,
                    'id_cuenta_origen' => $cuentaOrigen->id,
                    'id_moneda' => $monedaLocal->id,
                    'codigo' => 'tc_otros',
                    'importe' => $diferencia,
                    'fecha' => $fechaPago->toDateString(),
                    'cuotas' => 0,
                ]);
            } elseif ($otros !== null) {
                $nuevoImporte = Money::add((string) $otros->importe, $diferencia, 2);
                if (Money::isZero($nuevoImporte)) {
                    if ($otros->id_asiento) {
                        $this->asientoGenerator->deleteAsiento((int) $otros->id_asiento);
                    }
                    $otros->delete();
                    $otros = null;
                } else {
                    $otros->importe = $nuevoImporte;
                }
            }

            if ($otros !== null) {
                $otros->fecha = $fechaPago->toDateString();
                $otros->id_cuenta_origen = $cuentaOrigen->id;
                $otros->save();
                $this->asientoGenerator->replaceForPago($otros);
            }

            foreach ($pagos as $pago) {
                $pago->fecha = $fechaPago->toDateString();
                $pago->id_cuenta_origen = $cuentaOrigen->id;
                $pago->save();
                $this->asientoGenerator->replaceForPago($pago);
            }
        });
    }
}
