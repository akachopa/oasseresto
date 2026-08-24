<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Services;

use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Company\Models\Branch;
use App\Modules\Core\Enums\ApprovalStatus;
use App\Modules\Core\Enums\DeliveryStatus;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Finance\Models\CashAccount;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\Payable;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\Receipt;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Reporting\Models\DailyBusinessMetric;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesReturn;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * KPI owner dibaca dari tabel agregat harian, bukan dari query transaksi
 * mentah di dashboard (PLAN 12). Service ini mengisi ulang baris untuk satu
 * tanggal: angka aliran (omzet, kas masuk) mengikuti tanggal dokumen, angka
 * saldo (kas, piutang, stok) adalah snapshot saat agregasi dijalankan.
 */
class MetricsAggregationService
{
    public function __construct(private readonly ScopeManager $scope) {}

    public function aggregateCompany(int $companyId, Carbon|string|null $date = null): void
    {
        $date = Carbon::parse($date ?? now())->startOfDay();

        $this->scope->forCompany($companyId, function () use ($companyId, $date): void {
            $branchIds = Branch::query()->orderBy('id')->pluck('id')->all();

            foreach ($branchIds as $branchId) {
                $this->persist($companyId, (int) $branchId, $date, $this->compute((int) $branchId, $date));
            }

            $this->persist($companyId, null, $date, $this->compute(null, $date));
        });
    }

    /**
     * Agregasi ulang bila baris hari ini belum ada atau sudah lebih lama dari
     * cache_ttl, supaya dashboard tidak menampilkan angka kemarin.
     *
     * @param  array<int, int>|null  $branchIds
     */
    public function ensure(int $companyId, Carbon|string|null $date = null, ?array $branchIds = null): void
    {
        $date = Carbon::parse($date ?? now())->startOfDay();
        $ttl = (int) config('oasse.dashboard.cache_ttl', 300);

        $query = DailyBusinessMetric::query()
            ->where('company_id', $companyId)
            ->whereDate('metric_date', $date->toDateString());

        if ($branchIds === null) {
            $query->whereNull('branch_id');
        } else {
            $query->whereIn('branch_id', $branchIds);
        }

        $stale = $query->clone()
            ->where(function ($builder) use ($ttl): void {
                $builder->whereNull('computed_at')
                    ->orWhere('computed_at', '<', now()->subSeconds($ttl));
            })
            ->exists();

        if ($stale || ! $query->exists()) {
            $this->aggregateCompany($companyId, $date);
        }
    }

    /**
     * @param  array<int, int>|null  $branchIds
     */
    public function read(int $companyId, Carbon|string $date, ?array $branchIds = null): DailyBusinessMetric
    {
        $date = Carbon::parse($date)->startOfDay();

        if ($branchIds === null) {
            $row = DailyBusinessMetric::query()
                ->where('company_id', $companyId)
                ->whereNull('branch_id')
                ->whereDate('metric_date', $date->toDateString())
                ->first();

            return $row ?? $this->blank($companyId, $date);
        }

        $rows = DailyBusinessMetric::query()
            ->where('company_id', $companyId)
            ->whereIn('branch_id', $branchIds)
            ->whereDate('metric_date', $date->toDateString())
            ->get();

        if ($rows->isEmpty()) {
            return $this->blank($companyId, $date);
        }

        return $this->sumRows($rows, $companyId, $date);
    }

    /**
     * Total aliran selama rentang tanggal; saldo memakai baris terakhir.
     *
     * @param  array<int, int>|null  $branchIds
     */
    public function period(int $companyId, Carbon $from, Carbon $to, ?array $branchIds = null): DailyBusinessMetric
    {
        $query = DailyBusinessMetric::query()
            ->where('company_id', $companyId)
            ->whereDate('metric_date', '>=', $from->toDateString())
            ->whereDate('metric_date', '<=', $to->toDateString());

        if ($branchIds === null) {
            $query->whereNull('branch_id');
        } else {
            $query->whereIn('branch_id', $branchIds);
        }

        $rows = $query->orderBy('metric_date')->get();

        if ($rows->isEmpty()) {
            return $this->blank($companyId, $to);
        }

        return $this->sumRows($rows, $companyId, $to, snapshotFromLast: true);
    }

    /**
     * @param  array<int, int>|null  $branchIds
     * @return array<int, array{date: string, sales_amount: float, gross_profit: float}>
     */
    public function trend(int $companyId, Carbon $from, Carbon $to, ?array $branchIds = null): array
    {
        $query = DailyBusinessMetric::query()
            ->where('company_id', $companyId)
            ->whereDate('metric_date', '>=', $from->toDateString())
            ->whereDate('metric_date', '<=', $to->toDateString())
            ->orderBy('metric_date');

        if ($branchIds === null) {
            $query->whereNull('branch_id');
        } else {
            $query->whereIn('branch_id', $branchIds);
        }

        return $query
            ->get(['metric_date', 'branch_id', 'sales_amount', 'gross_profit'])
            ->groupBy(fn (DailyBusinessMetric $row) => $row->metric_date->toDateString())
            ->map(fn ($group, $date) => [
                'date' => $date,
                'sales_amount' => round((float) $group->sum('sales_amount'), 4),
                'gross_profit' => round((float) $group->sum('gross_profit'), 4),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, float|int>
     */
    private function compute(?int $branchId, Carbon $date): array
    {
        $day = $date->toDateString();

        $sales = SalesInvoice::query()
            ->where('status', DocumentStatus::Posted)
            ->whereDate('invoice_date', $day)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(total), 0) as amount, COUNT(*) as documents, COALESCE(SUM(cost_of_goods), 0) as cogs, COALESCE(SUM(subtotal), 0) as subtotal')
            ->first();

        $returns = (float) SalesReturn::query()
            ->where('status', DocumentStatus::Posted)
            ->whereDate('return_date', $day)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->sum('total');

        $receipts = (float) Receipt::query()
            ->where('status', DocumentStatus::Posted)
            ->whereDate('receipt_date', $day)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->sum('amount');

        $payments = (float) Payment::query()
            ->where('status', DocumentStatus::Posted)
            ->whereDate('payment_date', $day)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->sum('amount');

        $expenses = (float) Expense::query()
            ->where('status', DocumentStatus::Posted)
            ->whereDate('expense_date', $day)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->sum('total');

        $cash = (float) CashAccount::query()
            ->where('is_active', true)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId), fn ($q) => $q)
            ->sum('balance');

        $ar = Receivable::query()
            ->outstanding()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(outstanding_amount), 0) as total')
            ->first();

        $arOverdue = Receivable::query()
            ->outstanding()
            ->whereDate('due_date', '<', $day)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->sum('outstanding_amount');

        $ap = Payable::query()
            ->outstanding()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->sum('outstanding_amount');

        $apOverdue = Payable::query()
            ->outstanding()
            ->whereDate('due_date', '<', $day)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->sum('outstanding_amount');

        $inventory = StockBalance::query()
            ->when(
                $branchId,
                fn ($q) => $q->whereIn('warehouse_id', function ($sub) use ($branchId): void {
                    $sub->select('id')->from('warehouses')->where('branch_id', $branchId);
                }),
            )
            ->sum('total_value');

        $belowReorder = (int) DB::table('stock_balances')
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_balances.warehouse_id')
            ->where('stock_balances.company_id', $this->scope->companyId())
            ->whereNull('products.deleted_at')
            ->where('products.is_stocked', true)
            ->where('products.reorder_point', '>', 0)
            ->whereRaw('stock_balances.quantity - stock_balances.reserved_quantity <= products.reorder_point')
            ->when($branchId, fn ($q) => $q->where('warehouses.branch_id', $branchId))
            ->count();

        $pendingApprovals = ApprovalRequest::query()
            ->where('status', ApprovalStatus::Pending)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->count();

        $pendingPicking = Delivery::query()
            ->whereIn('status', [DeliveryStatus::Ready, DeliveryStatus::Picking, DeliveryStatus::Packed])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->count();

        $openPo = PurchaseOrder::query()
            ->whereIn('status', [DocumentStatus::Approved, DocumentStatus::PartiallyProcessed])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->count();

        $salesAmount = round((float) $sales->amount, 4);
        $cogs = round((float) $sales->cogs, 4);
        $subtotal = round((float) $sales->subtotal, 4);

        return [
            'sales_amount' => $salesAmount,
            'sales_count' => (int) $sales->documents,
            'cogs_amount' => $cogs,
            'gross_profit' => round($subtotal - $cogs, 4),
            'sales_return_amount' => round($returns, 4),
            'receipt_amount' => round($receipts, 4),
            'payment_amount' => round($payments, 4),
            'expense_amount' => round($expenses, 4),
            'cash_balance' => round($cash, 4),
            'ar_outstanding' => round((float) $ar->total, 4),
            'ar_overdue' => round((float) $arOverdue, 4),
            'ap_outstanding' => round((float) $ap, 4),
            'ap_overdue' => round((float) $apOverdue, 4),
            'inventory_value' => round((float) $inventory, 4),
            'below_reorder_count' => $belowReorder,
            'pending_approval_count' => $pendingApprovals,
            'pending_picking_count' => $pendingPicking,
            'open_po_count' => $openPo,
        ];
    }

    /**
     * @param  array<string, float|int>  $payload
     */
    private function persist(int $companyId, ?int $branchId, Carbon $date, array $payload): void
    {
        DailyBusinessMetric::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'metric_date' => $date->toDateString(),
            ],
            $payload + ['computed_at' => now()],
        );
    }

    private function blank(int $companyId, Carbon $date): DailyBusinessMetric
    {
        $metric = new DailyBusinessMetric([
            'company_id' => $companyId,
            'metric_date' => $date->toDateString(),
            'sales_amount' => 0,
            'sales_count' => 0,
            'cogs_amount' => 0,
            'gross_profit' => 0,
            'sales_return_amount' => 0,
            'receipt_amount' => 0,
            'payment_amount' => 0,
            'expense_amount' => 0,
            'cash_balance' => 0,
            'ar_outstanding' => 0,
            'ar_overdue' => 0,
            'ap_outstanding' => 0,
            'ap_overdue' => 0,
            'inventory_value' => 0,
            'below_reorder_count' => 0,
            'pending_approval_count' => 0,
            'pending_picking_count' => 0,
            'open_po_count' => 0,
        ]);

        return $metric;
    }

    /**
     * @param  iterable<int, DailyBusinessMetric>  $rows
     */
    private function sumRows(iterable $rows, int $companyId, Carbon $date, bool $snapshotFromLast = false): DailyBusinessMetric
    {
        $collection = collect($rows);
        $last = $collection->last();
        $metric = $this->blank($companyId, $date);

        $metric->sales_amount = round((float) $collection->sum('sales_amount'), 4);
        $metric->sales_count = (int) $collection->sum('sales_count');
        $metric->cogs_amount = round((float) $collection->sum('cogs_amount'), 4);
        $metric->gross_profit = round((float) $collection->sum('gross_profit'), 4);
        $metric->sales_return_amount = round((float) $collection->sum('sales_return_amount'), 4);
        $metric->receipt_amount = round((float) $collection->sum('receipt_amount'), 4);
        $metric->payment_amount = round((float) $collection->sum('payment_amount'), 4);
        $metric->expense_amount = round((float) $collection->sum('expense_amount'), 4);

        $snapshot = $snapshotFromLast && $last instanceof DailyBusinessMetric ? $last : $collection;

        if ($snapshotFromLast && $last instanceof DailyBusinessMetric) {
            $metric->cash_balance = $last->cash_balance;
            $metric->ar_outstanding = $last->ar_outstanding;
            $metric->ar_overdue = $last->ar_overdue;
            $metric->ap_outstanding = $last->ap_outstanding;
            $metric->ap_overdue = $last->ap_overdue;
            $metric->inventory_value = $last->inventory_value;
            $metric->below_reorder_count = $last->below_reorder_count;
            $metric->pending_approval_count = $last->pending_approval_count;
            $metric->pending_picking_count = $last->pending_picking_count;
            $metric->open_po_count = $last->open_po_count;
        } else {
            $metric->cash_balance = round((float) $collection->sum('cash_balance'), 4);
            $metric->ar_outstanding = round((float) $collection->sum('ar_outstanding'), 4);
            $metric->ar_overdue = round((float) $collection->sum('ar_overdue'), 4);
            $metric->ap_outstanding = round((float) $collection->sum('ap_outstanding'), 4);
            $metric->ap_overdue = round((float) $collection->sum('ap_overdue'), 4);
            $metric->inventory_value = round((float) $collection->sum('inventory_value'), 4);
            $metric->below_reorder_count = (int) $collection->sum('below_reorder_count');
            $metric->pending_approval_count = (int) $collection->sum('pending_approval_count');
            $metric->pending_picking_count = (int) $collection->sum('pending_picking_count');
            $metric->open_po_count = (int) $collection->sum('open_po_count');
        }

        return $metric;
    }
}
