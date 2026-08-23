<?php

namespace App\Http\Controllers;

use App\Services\Contabilidad\BalanceCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BalanceController extends Controller
{
    public function __construct(
        private BalanceCalculator $balanceCalculator,
    ) {}

    public function index(Request $request): Response
    {
        $desde = $request->filled('desde')
            ? Carbon::parse($request->input('desde'))
            : null;
        $hasta = $request->filled('hasta')
            ? Carbon::parse($request->input('hasta'))
            : now();

        $conSaldo = $request->boolean('con_saldo', true);
        $revaluar = $request->boolean('revaluar', true);

        $balance = $this->balanceCalculator->calcular($desde, $hasta, $conSaldo, $revaluar);

        return Inertia::render('Informes/Balance', [
            'balance' => $balance,
            'filters' => [
                'desde' => $desde?->toDateString(),
                'hasta' => $hasta->toDateString(),
                'con_saldo' => $conSaldo,
                'revaluar' => $revaluar,
            ],
        ]);
    }
}
