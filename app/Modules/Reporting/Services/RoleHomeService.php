<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Services;

use App\Models\User;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Scopes\LocationScope;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Finance\Models\CashAccount;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\Payable;
use App\Modules\Finance\Models\Receipt;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Queries\StockQuery;
use App\Modules\Purchase\Models\GoodsReceipt;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Purchase\Services\ReorderService;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesOrder;

/**
 * Widget home per peran (PLAN 13). Angka operasional diambil langsung dari
 * dokumen berjalan; KPI keuangan owner tetap lewat daily_business_metrics.
 */
class RoleHomeService
{
    public function __construct(
        private readonly LocationScope $location,
        private readonly StockQuery $stock,
        private readonly ReorderService $reorder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function warehouse(): array
    {
        $receipts = GoodsReceipt::query()->where('status', DocumentStatus::Draft);
        $this->location->applyWarehouse($receipts);

        $picking = Delivery::query()->open();
        $this->location->applyWarehouse($picking);

        $transfers = StockTransfer::query()->where('status', DocumentStatus::PartiallyProcessed);

        return [
            'pending_receipts' => (clone $receipts)->count(),
            'picking_queue' => (clone $picking)->count(),
            'below_reorder' => $this->stock->belowReorderPoint()->count(),
            'near_expiry' => $this->stock->nearExpiry()->count(),
            'in_transit' => (clone $transfers)->count(),
            'receipts' => $receipts->with('supplier')->latest()->limit(8)->get(),
            'deliveries' => $picking->with('customer')->latest()->limit(8)->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function purchasing(): array
    {
        $openPo = PurchaseOrder::query()
            ->whereIn('status', [DocumentStatus::Approved, DocumentStatus::PartiallyProcessed]);

        $latePo = (clone $openPo)
            ->whereNotNull('expected_date')
            ->whereDate('expected_date', '<', now()->toDateString());

        $draftReceipts = GoodsReceipt::query()->where('status', DocumentStatus::Draft);

        $overdueAp = Payable::query()
            ->outstanding()
            ->whereDate('due_date', '<', now()->toDateString());

        return [
            'reorder_count' => $this->reorder->query()->count(),
            'open_po' => (clone $openPo)->count(),
            'late_po' => (clone $latePo)->count(),
            'draft_receipts' => (clone $draftReceipts)->count(),
            'overdue_ap' => (clone $overdueAp)->sum('outstanding_amount'),
            'orders' => $openPo->with('supplier')->latest('order_date')->limit(8)->get(),
            'suggestions' => $this->reorder->suggestions()->take(8),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function salesman(User $user): array
    {
        $today = now()->toDateString();

        $invoices = SalesInvoice::query()
            ->where('salesman_id', $user->id)
            ->where('status', DocumentStatus::Posted);

        $orders = SalesOrder::query()
            ->where('salesman_id', $user->id)
            ->whereIn('status', [
                DocumentStatus::Submitted,
                DocumentStatus::Approved,
                DocumentStatus::PartiallyProcessed,
            ]);

        $overdue = Receivable::query()
            ->outstanding()
            ->where('salesman_id', $user->id)
            ->whereDate('due_date', '<', $today);

        return [
            'sales_today' => (float) (clone $invoices)->whereDate('invoice_date', $today)->sum('total'),
            'sales_month' => (float) (clone $invoices)
                ->whereDate('invoice_date', '>=', now()->startOfMonth()->toDateString())
                ->sum('total'),
            'open_orders' => (clone $orders)->count(),
            'overdue_ar' => (float) (clone $overdue)->sum('outstanding_amount'),
            'invoices' => (clone $invoices)->with('customer')->latest('invoice_date')->limit(8)->get(),
            'orders' => $orders->with('customer')->latest('order_date')->limit(8)->get(),
            'receivables' => $overdue->with('customer')->orderBy('due_date')->limit(8)->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function finance(): array
    {
        $today = now()->toDateString();

        $ar = Receivable::query()->outstanding();
        $ap = Payable::query()->outstanding();
        $expenses = Expense::query()->whereIn('status', [DocumentStatus::Submitted, DocumentStatus::Approved]);

        return [
            'cash' => (float) CashAccount::query()->where('is_active', true)->sum('balance'),
            'ar' => (float) (clone $ar)->sum('outstanding_amount'),
            'ar_overdue' => (float) (clone $ar)->whereDate('due_date', '<', $today)->sum('outstanding_amount'),
            'ap' => (float) (clone $ap)->sum('outstanding_amount'),
            'ap_overdue' => (float) (clone $ap)->whereDate('due_date', '<', $today)->sum('outstanding_amount'),
            'pending_expenses' => (clone $expenses)->count(),
            'receipts_today' => (float) Receipt::query()
                ->where('status', DocumentStatus::Posted)
                ->whereDate('receipt_date', $today)
                ->sum('amount'),
            'accounts' => CashAccount::query()->active()->orderByDesc('balance')->limit(6)->get(),
            'overdue_ar' => (clone $ar)->with('customer')->whereDate('due_date', '<', $today)->orderBy('due_date')->limit(8)->get(),
            'overdue_ap' => (clone $ap)->with('supplier')->whereDate('due_date', '<', $today)->orderBy('due_date')->limit(8)->get(),
        ];
    }
}
