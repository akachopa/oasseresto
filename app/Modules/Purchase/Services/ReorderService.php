<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Services;

use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Services\ScopeManager;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rekomendasi pembelian dihitung dari saldo tersedia, titik reorder, dan
 * barang yang sudah dipesan tapi belum datang (PLAN 18), sehingga barang
 * tidak dipesan dua kali hanya karena PO sebelumnya belum tiba.
 */
class ReorderService
{
    public function __construct(private readonly ScopeManager $scope) {}

    /**
     * @return Collection<int, object>
     */
    public function suggestions(?int $warehouseId = null, ?int $branchId = null): Collection
    {
        return collect(
            DB::query()
                ->fromSub($this->projection($warehouseId, $branchId), 'suggestion')
                ->whereColumn('projected_quantity', '<=', 'reorder_point')
                ->orderByRaw('projected_quantity - reorder_point')
                ->get()
        )->map(function (object $row): object {
            $target = max((float) $row->maximum_stock, (float) $row->reorder_point * 2);
            $available = (float) $row->projected_quantity;

            $row->available = $available;
            $row->suggested_quantity = round(max($target - $available, (float) $row->reorder_point), 4);
            $row->estimated_cost = round($row->suggested_quantity * (float) $row->last_purchase_cost, 4);
            $row->is_critical = $available <= (float) $row->minimum_stock;

            return $row;
        });
    }

    /**
     * Saldo proyeksi = stok tersedia + barang dalam pesanan yang belum datang.
     */
    private function projection(?int $warehouseId, ?int $branchId): Builder
    {
        $balances = DB::table('stock_balances')
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as quantity, SUM(reserved_quantity) as reserved');

        $incoming = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->whereIn('purchase_orders.status', [
                DocumentStatus::Approved->value,
                DocumentStatus::PartiallyProcessed->value,
            ])
            ->when($warehouseId, fn ($q) => $q->where('purchase_orders.warehouse_id', $warehouseId))
            ->when($branchId, fn ($q) => $q->where('purchase_orders.branch_id', $branchId))
            ->groupBy('purchase_order_items.product_id')
            ->selectRaw('
                purchase_order_items.product_id,
                SUM(purchase_order_items.base_quantity - purchase_order_items.received_base_quantity) as quantity
            ');

        $preferredSupplier = DB::table('supplier_products')
            ->join('suppliers', 'suppliers.id', '=', 'supplier_products.supplier_id')
            ->whereNull('suppliers.deleted_at')
            ->orderBy('supplier_products.product_id')
            ->orderByDesc('supplier_products.is_preferred')
            ->orderBy('supplier_products.last_price')
            ->selectRaw('DISTINCT ON (supplier_products.product_id)
                supplier_products.product_id,
                suppliers.id as supplier_id,
                suppliers.name as supplier_name');

        return DB::table('products')
            ->leftJoinSub($balances, 'balances', 'balances.product_id', '=', 'products.id')
            ->leftJoinSub($incoming, 'incoming', 'incoming.product_id', '=', 'products.id')
            ->leftJoinSub($preferredSupplier, 'preferred', 'preferred.product_id', '=', 'products.id')
            ->leftJoin('units', 'units.id', '=', 'products.base_unit_id')
            ->when($this->scope->companyId(), fn ($q, $companyId) => $q->where('products.company_id', $companyId))
            ->where('products.is_active', true)
            ->where('products.is_stocked', true)
            ->whereNull('products.deleted_at')
            ->select([
                'products.id',
                'products.company_id',
                'products.sku',
                'products.name',
                'products.reorder_point',
                'products.minimum_stock',
                'products.maximum_stock',
                'products.lead_time_days',
                'products.last_purchase_cost',
                'units.code as unit_code',
                'preferred.supplier_id',
                'preferred.supplier_name',
                DB::raw('COALESCE(balances.quantity, 0) as on_hand'),
                DB::raw('COALESCE(balances.reserved, 0) as reserved'),
                DB::raw('COALESCE(incoming.quantity, 0) as incoming'),
                DB::raw('
                    COALESCE(balances.quantity, 0)
                    - COALESCE(balances.reserved, 0)
                    + COALESCE(incoming.quantity, 0) as projected_quantity
                '),
            ]);
    }
}
