<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cotizacion extends LegacyModel
{

    protected $table = 'cotizaciones';

    protected $fillable = [
        'fecha',
        'id_moneda',
        'compra',
        'venta',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'id_moneda');
    }
}
