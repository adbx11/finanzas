<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Asiento extends LegacyModel
{

    protected $table = 'asientos';

    protected $fillable = [
        'id_origen',
        'descripcion',
        'comentarios',
        'fecha',
        'fecha_creacion',
        'fecha_confirmacion',
        'ejercicio',
        'cuota',
        'confirmado',
        'id_movimiento',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_creacion' => 'datetime',
        'fecha_confirmacion' => 'datetime',
        'confirmado' => 'boolean',
        'ejercicio' => 'integer',
        'cuota' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(AsientoItem::class, 'id_asiento');
    }
}
