<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Controllers;

use App\Modules\Accounting\Services\FinancialReportService;
use App\Modules\Core\Services\ScopeManager;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportController
{
    public function __construct(
        private readonly FinancialReportService $reports,
        private readonly ScopeManager $scope,
    ) {}

    public function home(): View
    {
        $company = $this->scope->company();
        $from = now()->startOfMonth();
        $to = now()->endOfMonth();
        $tb = $this->reports->trialBalance($company, $from, $to);
        $pnl = $this->reports->profitLoss($company, $from, $to);

        return view('accounting.home', [
            'trialBalance' => $tb,
            'profitLoss' => $pnl,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function trialBalance(Request $request): View
    {
        [$from, $to] = $this->range($request);

        return view('accounting.report.trial-balance', [
            'from' => $from,
            'to' => $to,
            'report' => $this->reports->trialBalance($this->scope->company(), $from, $to),
        ]);
    }

    public function profitLoss(Request $request): View
    {
        [$from, $to] = $this->range($request);

        return view('accounting.report.profit-loss', [
            'from' => $from,
            'to' => $to,
            'report' => $this->reports->profitLoss($this->scope->company(), $from, $to),
        ]);
    }

    public function balanceSheet(Request $request): View
    {
        $asOf = Carbon::parse($request->date('as_of') ?: now())->endOfDay();

        return view('accounting.report.balance-sheet', [
            'asOf' => $asOf,
            'report' => $this->reports->balanceSheet($this->scope->company(), $asOf),
        ]);
    }

    public function cashFlow(Request $request): View
    {
        [$from, $to] = $this->range($request);

        return view('accounting.report.cash-flow', [
            'from' => $from,
            'to' => $to,
            'report' => $this->reports->cashFlow($this->scope->company(), $from, $to),
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        $from = $request->date('from') ?: now()->startOfMonth();
        $to = $request->date('to') ?: now()->endOfMonth();

        return [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()];
    }
}
