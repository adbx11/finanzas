<?php

namespace App\Http\Controllers;

use App\Services\Crypto\CryptoService;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CryptoReportController extends Controller
{
    public function __construct(
        private CryptoService $cryptoService,
    ) {}

    public function index(Request $request): Response
    {
        $coin = strtoupper((string) $request->input('coin', 'BTC'));
        $defaultPrice = $this->cryptoService->currentPriceUsd($coin) ?? '88000';
        $priceUsd = Money::parse($request->input('price_usd')) ?? $defaultPrice;
        $yearFrom = $request->filled('year_from') ? (int) $request->input('year_from') : null;
        $yearTo = $request->filled('year_to') ? (int) $request->input('year_to') : null;

        $report = $this->cryptoService->yearlyReport($coin, $priceUsd, $yearFrom, $yearTo);

        return Inertia::render('Informes/Crypto', [
            'report' => $report,
            'filters' => [
                'coin' => $report['coin'],
                'price_usd' => $report['price_usd'],
                'year_from' => $yearFrom,
                'year_to' => $yearTo,
            ],
            'coins' => $this->cryptoService->distinctCoins(),
        ]);
    }
}
