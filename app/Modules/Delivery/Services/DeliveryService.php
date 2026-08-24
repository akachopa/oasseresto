<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Services;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DeliveryStatus;
use App\Modules\Core\Enums\InventoryTransactionType;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\DeliveryItem;
use App\Modules\Inventory\Services\StockMovement;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Product\Models\Product;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderItem;
use App\Modules\Sales\Services\SalesOrderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Surat jalan adalah satu-satunya jalan stok penjualan keluar (PLAN 27).
 * Alurnya: dibuat dari SO -> picking (batch dipilih FEFO) -> dikirim (stok
 * berkurang) -> diterima customer.
 */
class DeliveryService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly StockService $stock,
        private readonly SalesOrderService $orders,
    ) {}

    /**
     * Baris surat jalan disiapkan dari sisa order yang belum dikirim, supaya
     * pengiriman bertahap tidak pernah melebihi yang dipesan.
     *
     * @return array<int, array<string, mixed>>
     */
    public function draftItemsFromOrder(SalesOrder $order): array
    {
        $order->load('items.product');

        return $order->items
            ->filter(fn (SalesOrderItem $item) => $item->outstandingBaseQuantity() > 0)
            ->map(fn (SalesOrderItem $item) => [
                'sales_order_item_id' => $item->getKey(),
                'product_id' => $item->product_id,
                'unit_id' => $item->unit_id,
                'quantity' => $item->product->fromBaseQuantity($item->outstandingBaseQuantity(), $item->unit_id),
                'batch_id' => null,
                'note' => null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, sales_order_item_id?: ?int, batch_id?: ?int, note?: ?string}>  $items
     */
    public function save(?Delivery $delivery, array $attributes, array $items): Delivery
    {
        return DB::transaction(function () use ($delivery, $attributes, $items): Delivery {
            if ($delivery !== null && ! $delivery->isEditable()) {
                throw new RuntimeException('Surat jalan yang sudah dikirim tidak bisa diubah.');
            }

            $order = isset($attributes['sales_order_id'])
                ? SalesOrder::findOrFail($attributes['sales_order_id'])
                : null;

            if ($order !== null && ! $order->isDeliverable()) {
                throw new RuntimeException('Sales order ini belum disetujui atau sudah selesai.');
            }

            $warehouse = Warehouse::findOrFail($attributes['warehouse_id'] ?? $order?->warehouse_id);
            $date = Carbon::parse($attributes['delivery_date']);

            $delivery ??= new Delivery([
                'status' => DeliveryStatus::Ready,
                'created_by' => Auth::id(),
            ]);

            $delivery->fill([
                'sales_order_id' => $order?->getKey(),
                'customer_id' => $attributes['customer_id'] ?? $order?->customer_id,
                'customer_address_id' => $attributes['customer_address_id'] ?? $order?->customer_address_id,
                'warehouse_id' => $warehouse->getKey(),
                'branch_id' => $warehouse->branch_id,
                'delivery_date' => $date->toDateString(),
                'shipping_address' => $attributes['shipping_address'] ?? null,
                'driver_name' => $attributes['driver_name'] ?? null,
                'vehicle_number' => $attributes['vehicle_number'] ?? null,
                'note' => $attributes['note'] ?? null,
            ]);

            $delivery->number ??= $this->numbers->next('delivery', $warehouse->branch_id, $date);
            $delivery->save();

            $this->syncItems($delivery, $items);

            return $delivery->refresh();
        });
    }

    /**
     * Catat hasil picking. Kuantitas boleh lebih kecil dari rencana bila stok
     * fisik kurang; sisanya tetap terbuka di sales order.
     *
     * @param  array<int, float>  $picked  kuantitas satuan dasar per delivery_item_id
     * @param  array<int, ?int>  $batches  batch pilihan per delivery_item_id
     */
    public function pick(Delivery $delivery, array $picked, array $batches = []): Delivery
    {
        return DB::transaction(function () use ($delivery, $picked, $batches): Delivery {
            if (! $delivery->isPickable()) {
                throw new RuntimeException('Surat jalan ini tidak berada pada tahap picking.');
            }

            $delivery->load('items.product');

            foreach ($delivery->items as $item) {
                $quantity = round((float) ($picked[$item->getKey()] ?? $item->base_quantity), 4);

                if ($quantity < 0) {
                    throw new RuntimeException('Kuantitas picking tidak boleh minus.');
                }

                if ($quantity > $item->base_quantity + 0.0001) {
                    throw new RuntimeException(
                        "Picking {$item->product->sku} melebihi rencana pada surat jalan."
                    );
                }

                $item->picked_base_quantity = $quantity;
                $item->batch_id = $batches[$item->getKey()] ?? $item->batch_id;
                $item->save();
            }

            $delivery->status = DeliveryStatus::Packed;
            $delivery->picked_by = Auth::id();
            $delivery->picked_at = now();
            $delivery->save();

            return $delivery->refresh();
        });
    }

    /**
     * Kirim barang: stok keluar, reservasi dilepas, dan sisa order diperbarui
     * sehingga kekurangan kirim langsung terlihat.
     */
    public function dispatch(Delivery $delivery): Delivery
    {
        return DB::transaction(function () use ($delivery): Delivery {
            if (! $delivery->isDispatchable()) {
                throw new RuntimeException('Hanya surat jalan yang sudah dipicking bisa dikirim.');
            }

            $delivery->load('items.product', 'order.items');

            $totalValue = 0.0;

            foreach ($delivery->items as $item) {
                $quantity = $item->shippedBaseQuantity();

                if ($quantity <= 0) {
                    continue;
                }

                $ledgers = $this->stock->issue(new StockMovement(
                    product: $item->product,
                    warehouseId: (int) $delivery->warehouse_id,
                    type: InventoryTransactionType::Sale,
                    quantity: $quantity,
                    unitId: (int) $item->product->base_unit_id,
                    batchId: $item->batch_id,
                    documentType: 'delivery',
                    documentId: (int) $delivery->getKey(),
                    documentNumber: $delivery->number,
                    date: $delivery->delivery_date,
                    branchId: $delivery->branch_id,
                    note: $item->note,
                ));

                $cost = round((float) collect($ledgers)->sum('total_cost'), 4);

                $item->unit_cost = $quantity > 0 ? round($cost / $quantity, 4) : 0.0;
                $item->total_cost = $cost;
                $item->batch_id ??= $ledgers[0]->batch_id;
                $item->save();

                $totalValue += $cost;

                $this->applyToOrderItem($item, $quantity);
            }

            $delivery->status = DeliveryStatus::Dispatched;
            $delivery->total_value = round($totalValue, 4);
            $delivery->dispatched_at = now();
            $delivery->save();

            if ($delivery->order !== null) {
                $this->orders->refreshDeliveryStatus($delivery->order);
            }

            return $delivery->refresh();
        });
    }

    public function complete(Delivery $delivery, ?string $recipient = null): Delivery
    {
        if (! $delivery->isCompletable()) {
            throw new RuntimeException('Hanya surat jalan yang sudah dikirim bisa diselesaikan.');
        }

        $delivery->status = DeliveryStatus::Delivered;
        $delivery->recipient_name = $recipient;
        $delivery->delivered_at = now();
        $delivery->save();

        return $delivery;
    }

    /**
     * Gagal kirim: barang kembali ke gudang, jadi stok dimasukkan ulang dan
     * sisa order dibuka kembali agar bisa dikirim lain hari (PLAN 27).
     */
    public function fail(Delivery $delivery, string $reason): Delivery
    {
        return DB::transaction(function () use ($delivery, $reason): Delivery {
            if ($delivery->status !== DeliveryStatus::Dispatched) {
                throw new RuntimeException('Hanya surat jalan dalam perjalanan bisa ditandai gagal kirim.');
            }

            $delivery->load('items.product', 'order.items');

            foreach ($delivery->items as $item) {
                $quantity = $item->shippedBaseQuantity();

                if ($quantity <= 0) {
                    continue;
                }

                $this->stock->receive(new StockMovement(
                    product: $item->product,
                    warehouseId: (int) $delivery->warehouse_id,
                    type: InventoryTransactionType::SaleReturn,
                    quantity: $quantity,
                    unitId: (int) $item->product->base_unit_id,
                    unitCost: $item->unit_cost,
                    batchId: $item->batch_id,
                    documentType: 'delivery_failed',
                    documentId: (int) $delivery->getKey(),
                    documentNumber: $delivery->number,
                    date: now(),
                    branchId: $delivery->branch_id,
                    note: $reason,
                ));

                $orderItem = $item->orderItem;

                if ($orderItem !== null) {
                    $orderItem->delivered_base_quantity = max(
                        0,
                        round($orderItem->delivered_base_quantity - $quantity, 4),
                    );
                    $orderItem->save();
                }
            }

            $delivery->status = DeliveryStatus::Failed;
            $delivery->failure_reason = $reason;
            $delivery->save();

            if ($delivery->order !== null) {
                $this->orders->refreshDeliveryStatus($delivery->order);
            }

            return $delivery->refresh();
        });
    }

    public function cancel(Delivery $delivery): Delivery
    {
        if (! $delivery->isEditable()) {
            throw new RuntimeException('Surat jalan yang sudah dikirim tidak bisa dibatalkan.');
        }

        $delivery->items()->delete();
        $delivery->delete();

        return $delivery;
    }

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, sales_order_item_id?: ?int, batch_id?: ?int, note?: ?string}>  $items
     */
    private function syncItems(Delivery $delivery, array $items): void
    {
        $delivery->items()->delete();

        foreach ($items as $row) {
            $product = Product::findOrFail($row['product_id']);
            $unitId = (int) ($row['unit_id'] ?: $product->base_unit_id);
            $quantity = (float) $row['quantity'];
            $baseQuantity = $product->toBaseQuantity($quantity, $unitId);

            $orderItem = isset($row['sales_order_item_id'])
                ? SalesOrderItem::find($row['sales_order_item_id'])
                : null;

            if ($orderItem !== null && $baseQuantity > $orderItem->outstandingBaseQuantity() + 0.0001) {
                throw new RuntimeException(
                    "Pengiriman {$product->sku} melebihi sisa sales order."
                );
            }

            DeliveryItem::create([
                'company_id' => $delivery->company_id,
                'delivery_id' => $delivery->getKey(),
                'sales_order_item_id' => $orderItem?->getKey(),
                'product_id' => $product->getKey(),
                'unit_id' => $unitId,
                'batch_id' => $row['batch_id'] ?? null,
                'quantity' => $quantity,
                'base_quantity' => $baseQuantity,
                'picked_base_quantity' => 0,
                'note' => $row['note'] ?? null,
            ]);
        }
    }

    /**
     * Reservasi dilepas sebesar barang yang benar-benar keluar, karena stok
     * fisiknya sudah berkurang dan tidak perlu ditahan lagi.
     */
    private function applyToOrderItem(DeliveryItem $item, float $quantity): void
    {
        $orderItem = $item->orderItem;

        if ($orderItem === null) {
            return;
        }

        $release = min($orderItem->reserved_base_quantity, $quantity);

        if ($release > 0) {
            $this->stock->release(
                (int) $orderItem->product_id,
                (int) $item->delivery->warehouse_id,
                $release,
            );

            $orderItem->reserved_base_quantity = round($orderItem->reserved_base_quantity - $release, 4);
        }

        $orderItem->delivered_base_quantity = round($orderItem->delivered_base_quantity + $quantity, 4);
        $orderItem->save();
    }
}
