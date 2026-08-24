<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Controllers;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Scopes\LocationScope;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Purchase\Models\GoodsReceipt;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Purchase\Services\GoodsReceiptService;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class GoodsReceiptController
{
    public function __construct(
        private readonly GoodsReceiptService $receipts,
        private readonly LocationScope $location,
    ) {}

    public function index(): View
    {
        return view('purchase.receipt.index', [
            'suppliers' => Supplier::active()->orderBy('name')->pluck('name', 'id'),
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
            'statuses' => self::statusOptions(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = GoodsReceipt::query()
            ->join('suppliers', 'suppliers.id', '=', 'goods_receipts.supplier_id')
            ->join('warehouses', 'warehouses.id', '=', 'goods_receipts.warehouse_id')
            ->leftJoin('purchase_orders', 'purchase_orders.id', '=', 'goods_receipts.purchase_order_id')
            ->select([
                'goods_receipts.*',
                'suppliers.name as supplier_name',
                'warehouses.name as warehouse_name',
                'purchase_orders.number as order_number',
            ]);

        $this->location->applyBranch($query, 'goods_receipts.branch_id', includeNull: true);

        return response()->json(
            ServerTable::of($query)
                ->searchable([
                    'goods_receipts.number', 'goods_receipts.supplier_do_number',
                    'suppliers.name', 'purchase_orders.number',
                ])
                ->orderable([
                    null, 'goods_receipts.number', 'goods_receipts.receipt_date',
                    'suppliers.name', 'warehouses.name', 'purchase_orders.number',
                    'goods_receipts.total_value', 'goods_receipts.status', null,
                ])
                ->filter('supplier', fn ($q, $value) => $q->where('goods_receipts.supplier_id', $value))
                ->filter('warehouse', fn ($q, $value) => $q->where('goods_receipts.warehouse_id', $value))
                ->filter('status', fn ($q, $value) => $q->where('goods_receipts.status', $value))
                ->transform(fn (GoodsReceipt $receipt) => [
                    'number' => '<a class="font-mono text-xs hover:text-brand-600" href="'
                        .route('purchase.receipts.detail', $receipt).'">'.e($receipt->number).'</a>',
                    'date' => $receipt->receipt_date->format('d/m/Y'),
                    'supplier' => e((string) $receipt->supplier_name),
                    'warehouse' => e((string) $receipt->warehouse_name),
                    'order' => $receipt->order_number
                        ? '<span class="font-mono text-xs">'.e((string) $receipt->order_number).'</span>'
                        : '<span class="text-muted">tanpa PO</span>',
                    'value' => Money::rupiah($receipt->total_value),
                    'status' => view('components.status-badge', ['status' => $receipt->status])->render(),
                    'aksi' => view('components.row-actions', [
                        'detail' => route('purchase.receipts.detail', $receipt),
                        'edit' => $receipt->status->isEditable() ? route('purchase.receipts.edit', $receipt) : null,
                        'delete' => $receipt->status->isEditable() ? route('purchase.receipts.hapus', $receipt) : null,
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(Request $request): View
    {
        $orderId = $request->integer('order') ?: null;

        if ($orderId !== null) {
            $order = PurchaseOrder::findOrFail($orderId);

            abort_unless($order->isReceivable(), 403);
        }

        return view('purchase.receipt.create', ['orderId' => $orderId]);
    }

    public function edit(GoodsReceipt $goodsReceipt): View
    {
        abort_unless($goodsReceipt->status->isEditable(), 403);

        return view('purchase.receipt.edit', ['receipt' => $goodsReceipt]);
    }

    public function detail(GoodsReceipt $goodsReceipt): View
    {
        $goodsReceipt->load([
            'items.product.baseUnit', 'items.unit', 'items.batch', 'items.orderItem',
            'supplier', 'warehouse', 'order', 'creator',
        ]);

        return view('purchase.receipt.detail', ['receipt' => $goodsReceipt]);
    }

    public function post(GoodsReceipt $goodsReceipt): RedirectResponse
    {
        try {
            $this->receipts->post($goodsReceipt);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Penerimaan diposting; batch terbentuk dan stok gudang bertambah.');
    }

    public function hapus(GoodsReceipt $goodsReceipt): RedirectResponse
    {
        try {
            $this->receipts->cancel($goodsReceipt);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Penerimaan dibatalkan.');
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            DocumentStatus::Draft->value => DocumentStatus::Draft->label(),
            DocumentStatus::Posted->value => DocumentStatus::Posted->label(),
            DocumentStatus::Cancelled->value => DocumentStatus::Cancelled->label(),
        ];
    }
}
