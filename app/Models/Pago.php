<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pago extends LegacyModel
{

    protected $table = 'pagos';

    protected $fillable = [
        'fecha',
        'id_cuenta_origen',
        'id_cuenta_concepto',
        'id_moneda',
        'id_asiento',
        'importe',
        'cotizacion',
        'comentarios',
        'cuotas',
        'cuota',
        'id_origen',
        'codigo',
    ];

    protected $casts = [
        'fecha' => 'date',
        'cuotas' => 'integer',
        'cuota' => 'integer',
    ];

    public function cuentaOrigen(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class, 'id_cuenta_origen');
    }

    public function cuentaConcepto(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class, 'id_cuenta_concepto');
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'id_moneda');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class, 'id_asiento');
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id_origen');
    }

    public function cuotasHijas(): HasMany
    {
        return $this->hasMany(self::class, 'id_origen');
    }
}
