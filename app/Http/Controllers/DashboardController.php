<?php

namespace App\Http\Controllers;

use App\Services\Contabilidad\DashboardService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService,
    ) {}

    public function index(Request $request): Response
    {
        $hasta = $this->parseHasta($request);
        $cryptoCoin = $this->parseCryptoCoin($request);

        return Inertia::render('Dashboard', [
            'filters' => [
                'hasta' => $hasta->toDateString(),
                'crypto_coin' => $cryptoCoin,
            ],
        ]);
    }

    /** @deprecated Prefer saldos + distribucion (paralelo como legacy). */
    public function finanzas(Request $request): JsonResponse
    {
        $hasta = $this->parseHasta($request);

        return response()->json(array_merge(
            $this->dashboardService->saldosSection($hasta),
            $this->dashboardService->distribucionSection($hasta),
        ));
    }

    public function saldos(Request $request): JsonResponse
    {
        return response()->json(
            $this->dashboardService->saldosSection($this->parseHasta($request))
        );
    }

    public function distribucion(Request $request): JsonResponse
    {
        return response()->json(
            $this->dashboardService->distribucionSection($this->parseHasta($request))
        );
    }

    public function crypto(Request $request): JsonResponse
    {
        return response()->json(
            $this->dashboardService->cryptoSection($this->parseCryptoCoin($request))
        );
    }

    public function cryptoWallets(Request $request): JsonResponse
    {
        $coin = $this->parseCryptoCoin($request);

        return response()->json([
            'crypto_coin' => $coin,
            'crypto_wallets' => $this->dashboardService->cryptoWallets($coin),
        ]);
    }

    private function parseHasta(Request $request): Carbon
    {
        return $request->filled('hasta')
            ? Carbon::parse($request->input('hasta'))->startOfDay()
            : now()->startOfDay();
    }

    private function parseCryptoCoin(Request $request): string
    {
        return $request->filled('crypto_coin')
            ? (string) $request->input('crypto_coin')
            : 'BTC';
    }
}
