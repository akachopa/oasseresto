<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Services;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Enums\InventoryTransactionType;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Inventory\Services\StockMovement;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Product\Models\Product;
use App\Modules\Purchase\Models\GoodsReceipt;
use App\Modules\Purchase\Models\GoodsReceiptItem;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Purchase\Models\PurchaseOrderItem;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Penerimaan barang adalah satu-satunya jalan stok pembelian masuk (PLAN 20).
 * Posting penerimaan membentuk batch, menulis kartu stok lewat StockService,
 * dan memperbarui sisa PO sehingga backorder terlihat apa adanya.
 */
class GoodsReceiptService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly StockService $stock,
        private readonly PurchaseOrderService $orders,
        private readonly SupplierPerformanceService $performance,
    ) {}

    /**
     * Siapkan draft penerimaan dari PO: kuantitas awal diisi sisa yang belum
     * diterima supaya petugas gudang hanya perlu mengoreksi bila ada selisih.
     *
     * @return array<int, array<string, mixed>>
     */
    public function draftItemsFromOrder(PurchaseOrder $order): array
    {
        $order->load('items.product');

        return $order->items
            ->filter(fn (PurchaseOrderItem $item) => $item->outstandingBaseQuantity() > 0)
            ->map(fn (PurchaseOrderItem $item) => [
                'purchase_order_item_id' => $item->getKey(),
                'product_id' => $item->product_id,
                'unit_id' => $item->unit_id,
                'quantity' => $item->product->fromBaseQuantity($item->outstandingBaseQuantity(), $item->unit_id),
                'rejected_base_quantity' => 0,
                'unit_cost' => $item->baseUnitCost(),
                'batch_number' => null,
                'expiry_date' => null,
                'note' => null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, purchase_order_item_id?: ?int, rejected_base_quantity?: ?float, unit_cost?: ?float, batch_number?: ?string, expiry_date?: ?string, note?: ?string}>  $items
     */
    public function save(?GoodsReceipt $receipt, array $attributes, array $items): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt, $attributes, $items): GoodsReceipt {
            if ($receipt !== null && ! $receipt->status->isEditable()) {
                throw new RuntimeException('Penerimaan yang sudah diposting tidak bisa diubah.');
            }

            $order = isset($attributes['purchase_order_id'])
                ? PurchaseOrder::findOrFail($attributes['purchase_order_id'])
                : null;

            if ($order !== null && ! $order->isReceivable()) {
                throw new RuntimeException('Purchase order ini belum disetujui atau sudah selesai.');
            }

            $warehouse = Warehouse::findOrFail($attributes['warehouse_id'] ?? $order?->warehouse_id);
            $supplier = Supplier::findOrFail($attributes['supplier_id'] ?? $order?->supplier_id);
            $date = Carbon::parse($attributes['receipt_date']);

            $receipt ??= new GoodsReceipt([
                'status' => DocumentStatus::Draft,
                'created_by' => Auth::id(),
            ]);

            $receipt->fill([
                'purchase_order_id' => $order?->getKey(),
                'supplier_id' => $supplier->getKey(),
                'warehouse_id' => $warehouse->getKey(),
                'branch_id' => $warehouse->branch_id,
                'receipt_date' => $date->toDateString(),
                'supplier_do_number' => $attributes['supplier_do_number'] ?? null,
                'note' => $attributes['note'] ?? null,
            ]);

            $receipt->number ??= $this->numbers->next('goods_receipt', $warehouse->branch_id, $date);
            $receipt->save();

            $this->syncItems($receipt, $items);

            return $receipt->refresh();
        });
    }

    /**
     * Posting penerimaan: stok masuk, batch terbentuk, sisa PO diperbarui.
     */
    public function post(GoodsReceipt $receipt): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt): GoodsReceipt {
            if ($receipt->status !== DocumentStatus::Draft) {
                throw new RuntimeException('Hanya penerimaan draft yang bisa diposting.');
            }

            $receipt->load('items.product', 'order.items');

            if ($receipt->items->isEmpty()) {
                throw new RuntimeException('Penerimaan tanpa barang tidak bisa diposting.');
            }

            $totalValue = 0.0;

            foreach ($receipt->items as $item) {
                $accepted = $item->acceptedBaseQuantity();

                if ($accepted <= 0) {
                    continue;
                }

                $this->assertWithinOrder($item, $accepted);

                $ledgers = $this->stock->receive(new StockMovement(
                    product: $item->product,
                    warehouseId: (int) $receipt->warehouse_id,
                    type: InventoryTransactionType::Purchase,
                    quantity: $accepted,
                    unitId: (int) $item->product->base_unit_id,
                    unitCost: $item->unit_cost,
                    batchNumber: $item->batch_number,
                    expiryDate: $item->expiry_date,
                    supplierId: (int) $receipt->supplier_id,
                    documentType: 'goods_receipt',
                    documentId: (int) $receipt->getKey(),
                    documentNumber: $receipt->number,
                    date: $receipt->receipt_date,
                    branchId: $receipt->branch_id,
                    note: $item->note,
                ));

                $item->batch_id = $ledgers[0]->batch_id;
                $item->total_cost = round($accepted * $item->unit_cost, 4);
                $item->save();

                $totalValue += $item->total_cost;

                $this->applyToOrderItem($item, $accepted);
            }

            $receipt->status = DocumentStatus::Posted;
            $receipt->total_value = round($totalValue, 4);
            $receipt->posted_by = Auth::id();
            $receipt->posted_at = now();
            $receipt->save();

            if ($receipt->order !== null) {
                $this->orders->refreshReceiptStatus($receipt->order);
            }

            $this->performance->refresh($receipt->supplier_id);

            return $receipt->refresh();
        });
    }

    public function cancel(GoodsReceipt $receipt): GoodsReceipt
    {
        if ($receipt->status !== DocumentStatus::Draft) {
            throw new RuntimeException('Penerimaan yang sudah diposting hanya bisa dikoreksi lewat retur pembelian.');
        }

        $receipt->status = DocumentStatus::Cancelled;
        $receipt->save();

        return $receipt;
    }

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, purchase_order_item_id?: ?int, rejected_base_quantity?: ?float, unit_cost?: ?float, batch_number?: ?string, expiry_date?: ?string, note?: ?string}>  $items
     */
    private function syncItems(GoodsReceipt $receipt, array $items): void
    {
        $receipt->items()->delete();
        $total = 0.0;

        foreach ($items as $row) {
            $product = Product::findOrFail($row['product_id']);
            $unitId = (int) ($row['unit_id'] ?: $product->base_unit_id);
            $quantity = (float) $row['quantity'];
            $baseQuantity = $product->toBaseQuantity($quantity, $unitId);
            $rejected = round((float) ($row['rejected_base_quantity'] ?? 0), 4);

            if ($rejected > $baseQuantity) {
                throw new RuntimeException("Jumlah ditolak untuk {$product->sku} melebihi jumlah diterima.");
            }

            $orderItem = isset($row['purchase_order_item_id'])
                ? PurchaseOrderItem::find($row['purchase_order_item_id'])
                : null;

            $unitCost = (float) ($row['unit_cost'] ?? $orderItem?->baseUnitCost() ?? $product->average_cost);

            GoodsReceiptItem::create([
                'company_id' => $receipt->company_id,
                'goods_receipt_id' => $receipt->getKey(),
                'purchase_order_item_id' => $orderItem?->getKey(),
                'product_id' => $product->getKey(),
                'unit_id' => $unitId,
                'quantity' => $quantity,
                'base_quantity' => $baseQuantity,
                'rejected_base_quantity' => $rejected,
                'unit_cost' => $unitCost,
                'total_cost' => round(($baseQuantity - $rejected) * $unitCost, 4),
                'batch_number' => $row['batch_number'] ?? null,
                'expiry_date' => $row['expiry_date'] ?? null,
                'note' => $row['note'] ?? null,
            ]);

            $total += ($baseQuantity - $rejected) * $unitCost;
        }

        $receipt->total_value = round($total, 4);
        $receipt->save();
    }

    /**
     * Penerimaan tidak boleh melebihi PO: kelebihan kirim harus lewat PO baru
     * atau revisi PO agar komitmen dan tagihan tetap sinkron.
     */
    private function assertWithinOrder(GoodsReceiptItem $item, float $accepted): void
    {
        $orderItem = $item->orderItem;

        if ($orderItem === null) {
            return;
        }

        if ($accepted > $orderItem->outstandingBaseQuantity() + 0.0001) {
            throw new RuntimeException(
                "Penerimaan {$item->product->sku} melebihi sisa purchase order."
            );
        }
    }

    private function applyToOrderItem(GoodsReceiptItem $item, float $accepted): void
    {
        $orderItem = $item->orderItem;

        if ($orderItem === null) {
            return;
        }

        $orderItem->received_base_quantity = round($orderItem->received_base_quantity + $accepted, 4);
        $orderItem->save();
    }
}
