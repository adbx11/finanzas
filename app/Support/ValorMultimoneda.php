<?php

namespace App\Support;

use App\Models\Moneda;
use App\Services\CotizacionService;
use Carbon\Carbon;

/**
 * Port de com.adb.finanzas.util.ValorMultimoneda.
 * Totales convertidos con cotización venta a la fecha (parity legacy, incl. quirks BTC en total_usd/eur).
 */
class ValorMultimoneda
{
    public string $ars = '0';

    public string $usd = '0';

    public string $eur = '0';

    public string $btc = '0';

    public string $total_ars = '0';

    public string $total_usd = '0';

    public string $total_eur = '0';

    public string $total_btc = '0';

    public function __construct(
        private CotizacionService $cotizacionService,
    ) {}

    /**
     * @param  array{ars?: mixed, usd?: mixed, eur?: mixed, btc?: mixed}  $row
     */
    public static function fromRow(CotizacionService $cotizacionService, array $row, Carbon $fecha): self
    {
        $v = new self($cotizacionService);
        $v->ars = self::num($row['ars'] ?? 0);
        $v->usd = self::num($row['usd'] ?? 0);
        $v->eur = self::num($row['eur'] ?? 0);
        $v->btc = self::num($row['btc'] ?? 0);
        $v->calcularTotales($fecha);

        return $v;
    }

    public static function zero(CotizacionService $cotizacionService): self
    {
        return new self($cotizacionService);
    }

    public function calcularTotales(Carbon $fecha): void
    {
        $cUsd = $this->rate('USD', $fecha);
        $cEur = $this->rate('EUR', $fecha);
        $cBtc = $this->rate('BTC', $fecha);

        $this->total_ars = Money::round(
            Money::add(
                Money::add(
                    Money::add($this->ars, Money::mul($this->usd, $cUsd, 8), 8),
                    Money::mul($this->eur, $cEur, 8),
                    8
                ),
                Money::mul($this->btc, $cBtc, 8),
                8
            ),
            2
        );

        // Legacy: último término btc*c_btc/c_btc ≈ btc
        $this->total_usd = Money::round(
            Money::add(
                Money::add(
                    Money::add($this->usd, Money::div($this->ars, $cUsd, 8), 8),
                    Money::div(Money::mul($this->eur, $cEur, 8), $cUsd, 8),
                    8
                ),
                Money::div(Money::mul($this->btc, $cBtc, 8), $cBtc, 8),
                8
            ),
            2
        );

        $this->total_eur = Money::round(
            Money::add(
                Money::add(
                    Money::add($this->eur, Money::div($this->ars, $cEur, 8), 8),
                    Money::div(Money::mul($this->usd, $cUsd, 8), $cEur, 8),
                    8
                ),
                Money::div(Money::mul($this->btc, $cBtc, 8), $cBtc, 8),
                8
            ),
            2
        );

        $this->total_btc = Money::round(
            Money::add(
                Money::add(
                    Money::add($this->btc, Money::div($this->ars, $cBtc, 8), 8),
                    Money::div(Money::mul($this->usd, $cUsd, 8), $cBtc, 8),
                    8
                ),
                Money::div(Money::mul($this->eur, $cEur, 8), $cBtc, 8),
                8
            ),
            8
        );
    }

    public function multiply(string $factor): void
    {
        foreach (['ars', 'usd', 'eur', 'btc', 'total_ars', 'total_usd', 'total_eur', 'total_btc'] as $k) {
            $this->{$k} = Money::mul($this->{$k}, $factor, 8);
        }
    }

    public function add(self $other, Carbon $fecha): void
    {
        $this->ars = Money::add($this->ars, $other->ars, 8);
        $this->usd = Money::add($this->usd, $other->usd, 8);
        $this->eur = Money::add($this->eur, $other->eur, 8);
        $this->btc = Money::add($this->btc, $other->btc, 8);
        $this->calcularTotales($fecha);
    }

    public function clonar(): self
    {
        $v = new self($this->cotizacionService);
        foreach (['ars', 'usd', 'eur', 'btc', 'total_ars', 'total_usd', 'total_eur', 'total_btc'] as $k) {
            $v->{$k} = $this->{$k};
        }

        return $v;
    }

    public function variacionPorcentual(self $anterior): self
    {
        $v = self::zero($this->cotizacionService);
        foreach (['ars', 'usd', 'eur', 'btc', 'total_ars', 'total_usd', 'total_eur', 'total_btc'] as $k) {
            $v->{$k} = $this->pct($anterior->{$k}, $this->{$k});
        }

        return $v;
    }

    /**
     * @return array<string, string>
     */
    public function toPrefixedArray(string $prefix): array
    {
        $out = [];
        foreach (['ars', 'usd', 'eur', 'btc', 'total_ars', 'total_usd', 'total_eur', 'total_btc'] as $k) {
            $out[$prefix.$k] = Money::round($this->{$k}, in_array($k, ['btc', 'total_btc'], true) ? 8 : 2);
        }

        return $out;
    }

    private function pct(string $anterior, string $actual): string
    {
        if (Money::isZero($anterior)) {
            return '0';
        }

        return Money::round(
            Money::mul(
                Money::sub(Money::div($actual, $anterior, 8), '1', 8),
                '100',
                8
            ),
            4
        );
    }

    private function rate(string $codigo, Carbon $fecha): string
    {
        $moneda = Moneda::query()->where('codigo', $codigo)->first();
        if (! $moneda) {
            return '1';
        }

        $rate = $this->cotizacionService->getRateForDate($moneda, $fecha);

        return Money::isZero($rate) ? '1' : $rate;
    }

    private static function num(mixed $n): string
    {
        if ($n === null || $n === '') {
            return '0';
        }

        return (string) $n;
    }
}
