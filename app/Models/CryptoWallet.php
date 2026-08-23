<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CryptoWallet extends LegacyModel
{
    protected $table = 'crypto_wallets';

    protected $fillable = [
        'codigo',
        'descripcion',
        'id_coin',
        'habilitada',
    ];

    protected $casts = [
        'habilitada' => 'boolean',
        'id_coin' => 'integer',
    ];

    public function coin(): BelongsTo
    {
        return $this->belongsTo(CryptoCoin::class, 'id_coin');
    }

    public function scopeHabilitadas(Builder $query): Builder
    {
        return $query->where('habilitada', true);
    }
}
