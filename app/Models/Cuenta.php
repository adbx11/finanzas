<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cuenta extends LegacyModel
{
    /** Patrones fcodigo legacy (SQL LIKE: `_` = un carácter). */
    public const FCODIGO_INGRESO_CONCEPTO = '4._.__.__,5.9.00.00';

    public const FCODIGO_INGRESO_DESTINO = '1.1.01.__,5.9.00.00';

    public const FCODIGO_PAGO_CONCEPTO = '5._.__.__,2.1.01.__,4.2.01.00,4.2.99.__';

    public const FCODIGO_PAGO_ORIGEN = '1.1.01.__,2.1.01.__';

    protected $table = 'cuentas';

    protected $fillable = [
        'id_superior',
        'id_moneda',
        'codigo',
        'descripcion',
        'tipo_estado',
        'tipo_cuenta',
        'nivel',
        'imputable',
        'clase',
        'habilitada',
    ];

    protected $casts = [
        'imputable' => 'boolean',
        'habilitada' => 'boolean',
        'nivel' => 'integer',
    ];

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'id_moneda');
    }

    public function superior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id_superior');
    }

    public function hijas(): HasMany
    {
        return $this->hasMany(self::class, 'id_superior');
    }

    public function scopeHabilitadas(Builder $query): Builder
    {
        return $query->where('habilitada', true);
    }

    public function scopeImputables(Builder $query): Builder
    {
        return $query->where('imputable', true);
    }

    /** Prefijo simple (`1.1` → `1.1%`). Preferir codigoPatterns para combos legacy. */
    public function scopeCodigoLike(Builder $query, ?string $prefix): Builder
    {
        if ($prefix) {
            $query->where('codigo', 'like', $prefix.'%');
        }

        return $query;
    }

    /**
     * Uno o más patrones SQL LIKE separados por coma (como fcodigo en CuentaABM).
     * Ej.: `1.1.01.__,2.1.01.__` incluye caja/bancos y tarjetas.
     */
    public function scopeCodigoPatterns(Builder $query, string|array|null $patterns): Builder
    {
        if ($patterns === null || $patterns === '') {
            return $query;
        }

        $list = is_array($patterns)
            ? $patterns
            : array_values(array_filter(array_map('trim', explode(',', (string) $patterns))));

        if ($list === []) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($list) {
            foreach ($list as $pattern) {
                $q->orWhere('codigo', 'like', $pattern);
            }
        });
    }

    public function scopeClase(Builder $query, ?string $clase): Builder
    {
        if ($clase) {
            $query->where('clase', $clase);
        }

        return $query;
    }

    public function getLabelAttribute(): string
    {
        return $this->codigo.' '.$this->descripcion;
    }
}
