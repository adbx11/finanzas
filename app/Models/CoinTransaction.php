<?php

namespace App\Models;

class CoinTransaction extends LegacyModel
{
    protected $table = 'coin_transactions';

    protected $fillable = [
        'coin',
        'wallet',
        'quantity',
        'ts',
        'price_btc',
        'price_usd',
    ];

    protected $casts = [
        'ts' => 'datetime',
    ];
}
