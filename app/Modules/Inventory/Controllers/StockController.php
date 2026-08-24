<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\InventoryTransactionType;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Inventory\Queries\StockQuery;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController
{
    public function __construct(private readonly StockQuery $query) {}

    public function index(): View
    {
        return view('inventory.stock.index', [
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
            'categories' => ProductCategory::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $showValue = auth()->user()->can('inventory.valuation.view');

        return response()->json(
            ServerTable::of($this->query->balances())
                ->searchable(['products.sku', 'products.name'])
                ->orderable([
                    null, 'products.sku', 'products.name', 'warehouses.name',
                    'stock_balances.quantity', 'stock_balances.reserved_quantity',
                    'products.reorder_point', $showValue ? 'stock_balances.total_value' : null, null, null,
                ])
                ->filter('warehouse', fn ($q, $value) => $q->where('stock_balances.warehouse_id', $value))
                ->filter('category', fn ($q, $value) => $q->where('products.product_category_id', $value))
                ->filter('condition', fn ($q, $value) => match ($value) {
                    'below_reorder' => $q->whereRaw('stock_balances.quantity - stock_balances.reserved_quantity <= products.reorder_point')
                        ->where('products.reorder_point', '>', 0),
                    'empty' => $q->where('stock_balances.quantity', '<=', 0),
                    'available' => $q->where('stock_balances.quantity', '>', 0),
                    default => $q,
                })
                ->transform(fn (StockBalance $balance) => [
                    'sku' => '<span class="font-mono text-xs">'.e((string) $balance->sku).'</span>',
                    'product' => '<a class="font-medium hover:text-brand-600" href="'
                        .route('inventory.stock.detail', $balance->product_id).'">'.e((string) $balance->product_name).'</a>',
                    'warehouse' => e((string) $balance->warehouse_name),
                    'quantity' => $this->quantityCell($balance),
                    'reserved' => Money::quantity($balance->reserved_quantity),
                    'reorder_point' => Money::quantity($balance->reorder_point),
                    'value' => $showValue
                        ? Money::rupiah($balance->total_value)
                        : '<span class="text-muted">-</span>',
                    'average_cost' => $showValue
                        ? Money::rupiah($balance->average_cost)
                        : '<span class="text-muted">-</span>',
                    'aksi' => view('components.row-actions', [
                        'detail' => route('inventory.stock.detail', $balance->product_id),
                    ])->render(),
                ])
                ->make($request),
        );
    }

    /**
     * Kartu stok satu produk: saldo per gudang plus riwayat pergerakannya.
     */
    public function detail(Product $product): View
    {
        $product->load('baseUnit', 'category', 'brand');

        return view('inventory.stock.detail', [
            'product' => $product,
            'balances' => $this->query->balances()->where('stock_balances.product_id', $product->getKey())->get(),
            'batches' => $this->query->batches()
                ->where('batches.product_id', $product->getKey())
                ->where('batches.quantity', '>', 0)
                ->fefo()
                ->get(),
            'movements' => $this->query->ledger()
                ->where('stock_ledgers.product_id', $product->getKey())
                ->orderByDesc('stock_ledgers.transaction_at')
                ->orderByDesc('stock_ledgers.id')
                ->limit(50)
                ->get(),
        ]);
    }

    public function ledger(): View
    {
        return view('inventory.stock.ledger', [
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
            'types' => InventoryTransactionType::cases(),
        ]);
    }

    public function ledgerData(Request $request): JsonResponse
    {
        return response()->json(
            ServerTable::of($this->query->ledger())
                ->searchable(['products.sku', 'products.name', 'stock_ledgers.document_number'])
                ->orderable([
                    null, 'stock_ledgers.transaction_at', 'products.sku', 'products.name',
                    'warehouses.name', 'stock_ledgers.transaction_type', 'stock_ledgers.base_quantity',
                    'stock_ledgers.unit_cost', 'stock_ledgers.balance_quantity', null,
                ])
                ->filter('warehouse', fn ($q, $value) => $q->where('stock_ledgers.warehouse_id', $value))
                ->filter('type', fn ($q, $value) => $q->where('stock_ledgers.transaction_type', $value))
                ->filter('from', fn ($q, $value) => $q->where('stock_ledgers.transaction_date', '>=', $value))
                ->filter('to', fn ($q, $value) => $q->where('stock_ledgers.transaction_date', '<=', $value))
                ->transform(fn (StockLedger $row) => [
                    'date' => $row->transaction_at->format('d/m/Y H:i'),
                    'sku' => '<span class="font-mono text-xs">'.e((string) $row->sku).'</span>',
                    'product' => e((string) $row->product_name)
                        .($row->batch_number ? '<span class="badge-muted ml-1">'.e((string) $row->batch_number).'</span>' : ''),
                    'warehouse' => e((string) $row->warehouse_name),
                    'type' => '<span class="badge-'.($row->base_quantity > 0 ? 'success' : 'warning').'">'
                        .e($row->transaction_type->label()).'</span>',
                    'quantity' => $this->movementCell($row),
                    'unit_cost' => Money::rupiah($row->unit_cost),
                    'balance' => Money::quantity($row->balance_quantity),
                    'document' => e((string) $row->document_number),
                ])
                ->make($request),
        );
    }

    public function batches(): View
    {
        return view('inventory.stock.batch', [
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function batchData(Request $request): JsonResponse
    {
        return response()->json(
            ServerTable::of($this->query->batches())
                ->searchable(['batches.batch_number', 'products.sku', 'products.name'])
                ->orderable([
                    null, 'batches.expiry_date', 'batches.batch_number', 'products.name',
                    'warehouses.name', 'batches.quantity', 'batches.unit_cost', null, null,
                ])
                ->filter('warehouse', fn ($q, $value) => $q->where('batches.warehouse_id', $value))
                ->filter('condition', fn ($q, $value) => match ($value) {
                    'expired' => $q->whereNotNull('batches.expiry_date')->where('batches.expiry_date', '<', now()->toDateString()),
                    'near_expiry' => $q->whereNotNull('batches.expiry_date')
                        ->whereBetween('batches.expiry_date', [
                            now()->toDateString(),
                            now()->addDays((int) config('oasse.inventory.near_expiry_days', 60))->toDateString(),
                        ]),
                    'available' => $q->where('batches.quantity', '>', 0),
                    default => $q,
                })
                ->transform(fn (Batch $batch) => [
                    'expiry' => $this->expiryCell($batch),
                    'batch_number' => '<span class="font-mono text-xs">'.e($batch->batch_number).'</span>',
                    'product' => '<a class="font-medium hover:text-brand-600" href="'
                        .route('inventory.stock.detail', $batch->product_id).'">'.e((string) $batch->product_name).'</a>',
                    'warehouse' => e((string) $batch->warehouse_name),
                    'quantity' => Money::quantity($batch->quantity),
                    'unit_cost' => Money::rupiah($batch->unit_cost),
                    'value' => Money::rupiah($batch->quantity * $batch->unit_cost),
                    'received' => $batch->received_date?->format('d/m/Y') ?? '-',
                    'aksi' => view('components.row-actions', [
                        'detail' => route('inventory.stock.detail', $batch->product_id),
                    ])->render(),
                ])
                ->make($request),
        );
    }

    private function quantityCell(StockBalance $balance): string
    {
        $available = $balance->availableQuantity();
        $needsAttention = $balance->reorder_point > 0 && $available <= $balance->reorder_point;

        return '<span class="'.($needsAttention ? 'text-negative font-semibold' : 'font-medium').'">'
            .Money::quantity($balance->quantity).'</span> '
            .'<span class="text-muted text-xs">'.e((string) $balance->unit_code).'</span>';
    }

    private function movementCell(StockLedger $row): string
    {
        $class = $row->base_quantity > 0 ? 'text-positive' : 'text-negative';

        return '<span class="'.$class.' font-medium">'
            .($row->base_quantity > 0 ? '+' : '').Money::quantity($row->base_quantity).'</span>';
    }

    private function expiryCell(Batch $batch): string
    {
        if ($batch->expiry_date === null) {
            return '<span class="text-muted">-</span>';
        }

        $label = $batch->expiry_date->format('d/m/Y');

        return match (true) {
            $batch->isExpired() => '<span class="badge-danger">'.$label.'</span>',
            $batch->isNearExpiry() => '<span class="badge-warning">'.$label.'</span>',
            default => $label,
        };
    }
}
