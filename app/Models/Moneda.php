<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Moneda extends LegacyModel
{

    protected $table = 'monedas';

    protected $fillable = [
        'codigo',
        'simbolo',
        'local',
    ];

    protected $casts = [
        'local' => 'boolean',
    ];

    public function cuentas(): HasMany
    {
        return $this->hasMany(Cuenta::class, 'id_moneda');
    }

    public static function local(): ?self
    {
        return static::query()->where('local', true)->first();
    }
}
