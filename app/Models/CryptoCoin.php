<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CryptoCoin extends LegacyModel
{
    protected $table = 'crypto_coins';

    protected $fillable = [
        'codigo',
        'descripcion',
        'habilitada',
    ];

    protected $casts = [
        'habilitada' => 'boolean',
    ];

    public function wallets(): HasMany
    {
        return $this->hasMany(CryptoWallet::class, 'id_coin');
    }

    public function scopeHabilitadas(Builder $query): Builder
    {
        return $query->where('habilitada', true);
    }
}
