<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsientoItem extends LegacyModel
{

    protected $table = 'asiento_items';

    protected $fillable = [
        'id_asiento',
        'id_cuenta',
        'id_moneda',
        'descripcion',
        'comentarios',
        'debe',
        'haber',
        'debe_origen',
        'haber_origen',
        'cotizacion',
        'cuota',
        'unidades',
    ];

    protected $casts = [
        'cuota' => 'integer',
        'unidades' => 'integer',
    ];

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class, 'id_asiento');
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class, 'id_cuenta');
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'id_moneda');
    }
}
