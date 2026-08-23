<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ingreso extends LegacyModel
{

    protected $table = 'ingresos';

    protected $fillable = [
        'fecha',
        'id_cuenta_destino',
        'id_cuenta_concepto',
        'id_moneda',
        'id_asiento',
        'importe',
        'cotizacion',
        'comentarios',
        'id_origen',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function cuentaDestino(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class, 'id_cuenta_destino');
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
}
