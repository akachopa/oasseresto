<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Controllers;

use App\Modules\Reporting\Services\InsightService;
use App\Modules\Reporting\Services\OwnerDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class InsightController
{
    public function __construct(
        private readonly InsightService $insight,
        private readonly OwnerDashboardService $dashboard,
    ) {}

    public function index(): View
    {
        $board = $this->dashboard->build();

        return view('reporting.insight.index', [
            'today' => $board['today'],
            'month' => $board['month'],
            'trend' => $board['trend'],
            'forecast' => $this->insight->cashForecast(),
        ]);
    }

    public function cashForecast(): View
    {
        return view('reporting.insight.cash-forecast', [
            'rows' => $this->insight->cashForecast(),
        ]);
    }

    public function stockMovement(Request $request): View
    {
        [$from, $to] = $this->range($request);

        return view('reporting.insight.stock-movement', [
            'from' => $from,
            'to' => $to,
            'rows' => $this->insight->stockMovement($from, $to),
        ]);
    }

    public function profitability(Request $request): View
    {
        [$from, $to] = $this->range($request);

        return view('reporting.insight.profitability', [
            'from' => $from,
            'to' => $to,
            'products' => $this->insight->profitabilityByProduct($from, $to),
            'branches' => $this->insight->profitabilityByBranch($from, $to),
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        $from = Carbon::parse($request->date('from') ?: now()->startOfMonth())->startOfDay();
        $to = Carbon::parse($request->date('to') ?: now())->endOfDay();

        return [$from, $to];
    }
}
