<?php

namespace App\Support;

class Money
{
    public static function format(?string $amount, int $decimals = 2): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        return number_format((float) $amount, $decimals, ',', '.');
    }

    public static function parse(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (str_contains($value, ',')) {
            // Formato AR: 1.234,56 → puntos = miles, coma = decimal
            $normalized = str_replace(['.', ' '], '', $value);
            $normalized = str_replace(',', '.', $normalized);
        } else {
            // Ya normalizado / US: 1234.56 — el punto es decimal
            $normalized = str_replace(' ', '', $value);
        }

        if (! is_numeric($normalized)) {
            return null;
        }

        return $normalized;
    }

    public static function round(string $amount, int $scale = 2): string
    {
        return bcadd($amount, '0', $scale);
    }

    public static function mul(string $a, string $b, int $scale = 4): string
    {
        return bcmul($a, $b, $scale);
    }

    public static function add(string $a, string $b, int $scale = 2): string
    {
        return bcadd($a, $b, $scale);
    }

    public static function sub(string $a, string $b, int $scale = 2): string
    {
        return bcsub($a, $b, $scale);
    }

    public static function div(string $a, string $b, int $scale = 4): string
    {
        if (bccomp($b, '0', $scale) === 0) {
            return '0';
        }

        return bcdiv($a, $b, $scale);
    }

    public static function isPositive(string $amount): bool
    {
        return bccomp($amount, '0', 8) > 0;
    }

    public static function isZero(string $amount): bool
    {
        return bccomp($amount, '0', 8) === 0;
    }
}
