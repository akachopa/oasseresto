<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Services;

use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Purchase\Models\GoodsReceipt;
use App\Modules\Purchase\Models\PurchaseInvoice;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Support\Facades\DB;

/**
 * Angka performa supplier dihitung ulang dari dokumen nyata, bukan diakumulasi
 * bertahap, supaya koreksi dokumen langsung tercermin di rapor supplier
 * (PLAN 21).
 */
class SupplierPerformanceService
{
    public function refresh(int $supplierId): void
    {
        $supplier = Supplier::withoutGlobalScopes()->find($supplierId);

        if ($supplier === null) {
            return;
        }

        $receipts = GoodsReceipt::withoutGlobalScopes()
            ->join('purchase_orders', 'purchase_orders.id', '=', 'goods_receipts.purchase_order_id')
            ->where('goods_receipts.supplier_id', $supplierId)
            ->where('goods_receipts.status', DocumentStatus::Posted)
            ->selectRaw('
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE purchase_orders.expected_date IS NULL
                    OR goods_receipts.receipt_date <= purchase_orders.expected_date) as on_time,
                COALESCE(AVG(goods_receipts.receipt_date - purchase_orders.order_date), 0) as lead_time
            ')
            ->first();

        $quality = DB::table('goods_receipt_items')
            ->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_items.goods_receipt_id')
            ->where('goods_receipts.supplier_id', $supplierId)
            ->where('goods_receipts.status', DocumentStatus::Posted->value)
            ->selectRaw('
                COALESCE(SUM(goods_receipt_items.base_quantity), 0) as total_quantity,
                COALESCE(SUM(goods_receipt_items.rejected_base_quantity), 0) as rejected_quantity
            ')
            ->first();

        $total = (int) ($receipts->total ?? 0);
        $totalQuantity = (float) ($quality->total_quantity ?? 0);
        $rejected = (float) ($quality->rejected_quantity ?? 0);

        $supplier->order_count = PurchaseOrder::withoutGlobalScopes()
            ->where('supplier_id', $supplierId)
            ->whereNotIn('status', [DocumentStatus::Draft, DocumentStatus::Cancelled, DocumentStatus::Rejected])
            ->count();

        $supplier->on_time_rate = $total > 0
            ? round((int) $receipts->on_time / $total * 100, 4)
            : 0;

        $supplier->quality_rate = $totalQuantity > 0
            ? round(($totalQuantity - $rejected) / $totalQuantity * 100, 4)
            : 0;

        $supplier->average_lead_time = round((float) ($receipts->lead_time ?? 0), 2);

        $supplier->total_purchase = (float) PurchaseInvoice::withoutGlobalScopes()
            ->where('supplier_id', $supplierId)
            ->where('status', DocumentStatus::Posted)
            ->sum('total');

        $supplier->outstanding_amount = (float) PurchaseInvoice::withoutGlobalScopes()
            ->where('supplier_id', $supplierId)
            ->where('status', DocumentStatus::Posted)
            ->sum('outstanding_amount');

        $supplier->saveQuietly();
    }
}
