<?php

namespace App\Models;

class Configuracion extends LegacyModel
{
    protected $table = 'configuracion';

    protected $primaryKey = 'id_configuracion';

    protected $fillable = [
        'clave',
        'valor',
        'tipo',
        'nombre',
        'orden',
        'grupo_orden',
        'grupo',
    ];
}
