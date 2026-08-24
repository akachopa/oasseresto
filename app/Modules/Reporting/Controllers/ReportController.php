<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Controllers;

use App\Modules\Customer\Models\Customer;
use App\Modules\Reporting\Services\InsightService;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportController
{
    public function __construct(private readonly InsightService $insight) {}

    public function sales(Request $request): View
    {
        [$from, $to] = $this->range($request);
        $rows = $this->insight->sales($from, $to);

        return view('reporting.report.sales', [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'total' => (float) $rows->sum('total'),
            'cogs' => (float) $rows->sum('cost_of_goods'),
        ]);
    }

    public function purchase(Request $request): View
    {
        [$from, $to] = $this->range($request);
        $rows = $this->insight->purchases($from, $to);

        return view('reporting.report.purchase', [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'total' => (float) $rows->sum('total'),
        ]);
    }

    public function inventoryValuation(): View
    {
        $rows = $this->insight->inventoryValuation();

        return view('reporting.report.inventory-valuation', [
            'rows' => $rows,
            'total' => (float) $rows->sum('total_value'),
        ]);
    }

    public function arAging(Request $request): View
    {
        $customerId = $request->integer('customer') ?: null;
        $groups = $this->insight->aging()->receivables(null, $customerId);

        return view('reporting.report.ar-aging', [
            'customers' => Customer::active()->orderBy('name')->pluck('name', 'id'),
            'customerId' => $customerId,
            'buckets' => $this->insight->aging()->buckets(),
            'groups' => $groups,
            'totals' => $this->insight->aging()->totals($groups),
        ]);
    }

    public function apAging(Request $request): View
    {
        $supplierId = $request->integer('supplier') ?: null;
        $groups = $this->insight->aging()->payables(null, $supplierId);

        return view('reporting.report.ap-aging', [
            'suppliers' => Supplier::query()->orderBy('name')->pluck('name', 'id'),
            'supplierId' => $supplierId,
            'buckets' => $this->insight->aging()->buckets(),
            'groups' => $groups,
            'totals' => $this->insight->aging()->totals($groups),
        ]);
    }

    public function profitByBranch(Request $request): View
    {
        [$from, $to] = $this->range($request);

        return view('reporting.report.profit-by-branch', [
            'from' => $from,
            'to' => $to,
            'rows' => $this->insight->profitabilityByBranch($from, $to),
        ]);
    }

    public function profitByProduct(Request $request): View
    {
        [$from, $to] = $this->range($request);

        return view('reporting.report.profit-by-product', [
            'from' => $from,
            'to' => $to,
            'rows' => $this->insight->profitabilityByProduct($from, $to),
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
