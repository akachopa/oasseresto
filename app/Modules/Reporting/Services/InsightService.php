<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Services;

use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Enums\InventoryTransactionType;
use App\Modules\Finance\Models\CashAccount;
use App\Modules\Finance\Models\Payable;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Finance\Services\AgingService;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Purchase\Models\PurchaseInvoice;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoiceItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan insight dan operasional Phase 6: ringkasan, proyeksi kas sederhana,
 * pergerakan stok, dan profitabilitas.
 */
class InsightService
{
    public function __construct(private readonly AgingService $aging) {}

    /**
     * @return array<int, array{horizon: int, cash: float, inflows: float, outflows: float, projected: float}>
     */
    public function cashForecast(?Carbon $asOf = null): array
    {
        $asOf ??= now()->startOfDay();
        $cash = (float) CashAccount::query()->where('is_active', true)->sum('balance');
        $horizons = array_map('intval', (array) config('oasse.dashboard.forecast_horizons', [7, 14, 30]));

        return array_map(function (int $days) use ($asOf, $cash): array {
            $until = $asOf->copy()->addDays($days)->toDateString();
            $from = $asOf->toDateString();

            $inflows = (float) Receivable::query()
                ->outstanding()
                ->whereDate('due_date', '>=', $from)
                ->whereDate('due_date', '<=', $until)
                ->sum('outstanding_amount');

            $outflows = (float) Payable::query()
                ->outstanding()
                ->whereDate('due_date', '>=', $from)
                ->whereDate('due_date', '<=', $until)
                ->sum('outstanding_amount');

            return [
                'horizon' => $days,
                'cash' => round($cash, 4),
                'inflows' => round($inflows, 4),
                'outflows' => round($outflows, 4),
                'projected' => round($cash + $inflows - $outflows, 4),
            ];
        }, $horizons);
    }

    /**
     * @return Collection<int, object>
     */
    public function stockMovement(Carbon $from, Carbon $to): Collection
    {
        return StockLedger::query()
            ->select([
                'transaction_date',
                'transaction_type',
                DB::raw('COALESCE(SUM(base_quantity), 0) as quantity'),
                DB::raw('COALESCE(SUM(total_cost), 0) as value'),
            ])
            ->whereDate('transaction_date', '>=', $from->toDateString())
            ->whereDate('transaction_date', '<=', $to->toDateString())
            ->groupBy('transaction_date', 'transaction_type')
            ->orderBy('transaction_date')
            ->orderBy('transaction_type')
            ->get()
            ->map(function (object $row): object {
                $type = $row->transaction_type instanceof InventoryTransactionType
                    ? $row->transaction_type
                    : InventoryTransactionType::tryFrom((string) $row->transaction_type);

                $row->type_label = $type?->label() ?? 'Lainnya';
                $row->quantity = (float) $row->quantity;
                $row->value = (float) $row->value;

                return $row;
            });
    }

    /**
     * @return Collection<int, object>
     */
    public function profitabilityByProduct(Carbon $from, Carbon $to): Collection
    {
        return SalesInvoiceItem::query()
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
            ->join('products', 'products.id', '=', 'sales_invoice_items.product_id')
            ->where('sales_invoices.status', DocumentStatus::Posted->value)
            ->whereDate('sales_invoices.invoice_date', '>=', $from->toDateString())
            ->whereDate('sales_invoices.invoice_date', '<=', $to->toDateString())
            ->groupBy('products.id', 'products.sku', 'products.name')
            ->orderByDesc(DB::raw('SUM(sales_invoice_items.line_total)'))
            ->limit(50)
            ->get([
                'products.id',
                'products.sku',
                'products.name',
                DB::raw('SUM(sales_invoice_items.line_total) as revenue'),
                DB::raw('SUM(sales_invoice_items.unit_cost * sales_invoice_items.base_quantity) as cogs'),
                DB::raw('SUM(sales_invoice_items.base_quantity) as quantity'),
            ])
            ->map(function (object $row): object {
                $row->revenue = (float) $row->revenue;
                $row->cogs = (float) $row->cogs;
                $row->quantity = (float) $row->quantity;
                $row->profit = round($row->revenue - $row->cogs, 4);
                $row->margin = $row->revenue > 0 ? round($row->profit / $row->revenue * 100, 4) : 0.0;

                return $row;
            });
    }

    /**
     * @return Collection<int, object>
     */
    public function profitabilityByBranch(Carbon $from, Carbon $to): Collection
    {
        return SalesInvoice::query()
            ->join('branches', 'branches.id', '=', 'sales_invoices.branch_id')
            ->where('sales_invoices.status', DocumentStatus::Posted)
            ->whereDate('sales_invoices.invoice_date', '>=', $from->toDateString())
            ->whereDate('sales_invoices.invoice_date', '<=', $to->toDateString())
            ->groupBy('branches.id', 'branches.code', 'branches.name')
            ->orderByDesc(DB::raw('SUM(sales_invoices.total)'))
            ->get([
                'branches.id',
                'branches.code',
                'branches.name',
                DB::raw('SUM(sales_invoices.total) as revenue'),
                DB::raw('SUM(sales_invoices.cost_of_goods) as cogs'),
                DB::raw('COUNT(*) as documents'),
            ])
            ->map(function (object $row): object {
                $row->revenue = (float) $row->revenue;
                $row->cogs = (float) $row->cogs;
                $row->profit = round($row->revenue - $row->cogs, 4);
                $row->margin = $row->revenue > 0 ? round($row->profit / $row->revenue * 100, 4) : 0.0;
                $row->documents = (int) $row->documents;

                return $row;
            });
    }

    /**
     * @return Collection<int, SalesInvoice>
     */
    public function sales(Carbon $from, Carbon $to): Collection
    {
        return SalesInvoice::query()
            ->with('customer', 'branch')
            ->where('status', DocumentStatus::Posted)
            ->whereDate('invoice_date', '>=', $from->toDateString())
            ->whereDate('invoice_date', '<=', $to->toDateString())
            ->orderByDesc('invoice_date')
            ->limit(200)
            ->get();
    }

    /**
     * @return Collection<int, PurchaseInvoice>
     */
    public function purchases(Carbon $from, Carbon $to): Collection
    {
        return PurchaseInvoice::query()
            ->with('supplier', 'branch')
            ->where('status', DocumentStatus::Posted)
            ->whereDate('invoice_date', '>=', $from->toDateString())
            ->whereDate('invoice_date', '<=', $to->toDateString())
            ->orderByDesc('invoice_date')
            ->limit(200)
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function inventoryValuation(): Collection
    {
        return StockBalance::query()
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_balances.warehouse_id')
            ->whereNull('products.deleted_at')
            ->where('stock_balances.quantity', '>', 0)
            ->orderByDesc('stock_balances.total_value')
            ->limit(200)
            ->get([
                'products.sku',
                'products.name as product_name',
                'warehouses.name as warehouse_name',
                'stock_balances.quantity',
                'stock_balances.average_cost',
                'stock_balances.total_value',
            ]);
    }

    public function aging(): AgingService
    {
        return $this->aging;
    }
}
