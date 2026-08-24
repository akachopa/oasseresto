<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Queries;

use App\Modules\Core\Scopes\LocationScope;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLedger;
use Illuminate\Database\Eloquent\Builder;

/**
 * Semua pembacaan stok lewat query object ini supaya pembatasan gudang dan
 * join yang dipakai daftar maupun laporan tetap satu versi (PLAN 49 dan 64).
 */
class StockQuery
{
    public function __construct(private readonly LocationScope $location) {}

    public function balances(): Builder
    {
        $query = StockBalance::query()
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_balances.warehouse_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
            ->leftJoin('units', 'units.id', '=', 'products.base_unit_id')
            ->whereNull('products.deleted_at')
            ->select([
                'stock_balances.*',
                'products.sku',
                'products.name as product_name',
                'products.reorder_point',
                'products.minimum_stock',
                'products.is_stocked',
                'product_categories.name as category_name',
                'warehouses.name as warehouse_name',
                'units.code as unit_code',
            ]);

        return $this->location->applyWarehouse($query, 'stock_balances.warehouse_id');
    }

    public function ledger(): Builder
    {
        $query = StockLedger::query()
            ->join('products', 'products.id', '=', 'stock_ledgers.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_ledgers.warehouse_id')
            ->leftJoin('units', 'units.id', '=', 'stock_ledgers.unit_id')
            ->leftJoin('batches', 'batches.id', '=', 'stock_ledgers.batch_id')
            ->select([
                'stock_ledgers.*',
                'products.sku',
                'products.name as product_name',
                'warehouses.name as warehouse_name',
                'units.code as unit_code',
                'batches.batch_number',
            ]);

        return $this->location->applyWarehouse($query, 'stock_ledgers.warehouse_id');
    }

    public function batches(): Builder
    {
        $query = Batch::query()
            ->join('products', 'products.id', '=', 'batches.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'batches.warehouse_id')
            ->select([
                'batches.*',
                'products.sku',
                'products.name as product_name',
                'warehouses.name as warehouse_name',
            ]);

        return $this->location->applyWarehouse($query, 'batches.warehouse_id');
    }

    /**
     * Produk yang perlu dibeli: saldo tersedia sudah menyentuh titik reorder.
     */
    public function belowReorderPoint(): Builder
    {
        return $this->balances()
            ->where('products.is_stocked', true)
            ->where('products.reorder_point', '>', 0)
            ->whereRaw('stock_balances.quantity - stock_balances.reserved_quantity <= products.reorder_point');
    }

    public function nearExpiry(?int $days = null): Builder
    {
        $days ??= (int) config('oasse.inventory.near_expiry_days', 60);

        return $this->batches()
            ->where('batches.quantity', '>', 0)
            ->whereNotNull('batches.expiry_date')
            ->where('batches.expiry_date', '<=', now()->addDays($days)->toDateString());
    }

    /**
     * Barang tanpa pergerakan keluar dalam periode tertentu (PLAN 31).
     */
    public function deadStock(?int $days = null): Builder
    {
        $days ??= (int) config('oasse.inventory.dead_stock_days', 90);
        $since = now()->subDays($days)->toDateString();

        return $this->balances()
            ->where('stock_balances.quantity', '>', 0)
            ->whereNotExists(function ($query) use ($since): void {
                $query->selectRaw(1)
                    ->from('stock_ledgers')
                    ->whereColumn('stock_ledgers.product_id', 'stock_balances.product_id')
                    ->whereColumn('stock_ledgers.warehouse_id', 'stock_balances.warehouse_id')
                    ->where('stock_ledgers.base_quantity', '<', 0)
                    ->where('stock_ledgers.transaction_date', '>=', $since);
            });
    }
}
