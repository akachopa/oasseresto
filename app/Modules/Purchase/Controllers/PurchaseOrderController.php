<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Controllers;

use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Scopes\LocationScope;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Purchase\Models\PurchaseRequest;
use App\Modules\Purchase\Services\PurchaseOrderService;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PurchaseOrderController
{
    public function __construct(
        private readonly PurchaseOrderService $orders,
        private readonly LocationScope $location,
    ) {}

    public function index(): View
    {
        return view('purchase.order.index', [
            'suppliers' => Supplier::active()->orderBy('name')->pluck('name', 'id'),
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
            'statuses' => self::statusOptions(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = PurchaseOrder::query()
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->join('warehouses', 'warehouses.id', '=', 'purchase_orders.warehouse_id')
            ->select([
                'purchase_orders.*',
                'suppliers.name as supplier_name',
                'warehouses.name as warehouse_name',
            ]);

        $this->location->applyBranch($query, 'purchase_orders.branch_id', includeNull: true);

        return response()->json(
            ServerTable::of($query)
                ->searchable(['purchase_orders.number', 'suppliers.name', 'suppliers.code'])
                ->orderable([
                    null, 'purchase_orders.number', 'purchase_orders.order_date',
                    'suppliers.name', 'warehouses.name', 'purchase_orders.expected_date',
                    'purchase_orders.total', 'purchase_orders.status', null,
                ])
                ->filter('supplier', fn ($q, $value) => $q->where('purchase_orders.supplier_id', $value))
                ->filter('warehouse', fn ($q, $value) => $q->where('purchase_orders.warehouse_id', $value))
                ->filter('status', fn ($q, $value) => $q->where('purchase_orders.status', $value))
                ->filter('outstanding', fn ($q) => $q
                    ->whereIn('purchase_orders.status', [
                        DocumentStatus::Approved->value,
                        DocumentStatus::PartiallyProcessed->value,
                    ]))
                ->transform(fn (PurchaseOrder $order) => [
                    'number' => '<a class="font-mono text-xs hover:text-brand-600" href="'
                        .route('purchase.orders.detail', $order).'">'.e($order->number).'</a>',
                    'date' => $order->order_date->format('d/m/Y'),
                    'supplier' => e((string) $order->supplier_name),
                    'warehouse' => e((string) $order->warehouse_name),
                    'expected' => $order->expected_date?->format('d/m/Y') ?? '-',
                    'total' => Money::rupiah($order->total),
                    'status' => view('components.status-badge', ['status' => $order->status])->render(),
                    'aksi' => view('components.row-actions', [
                        'detail' => route('purchase.orders.detail', $order),
                        'edit' => $order->status->isEditable() ? route('purchase.orders.edit', $order) : null,
                        'delete' => $order->status->isEditable() ? route('purchase.orders.hapus', $order) : null,
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(Request $request): View
    {
        return view('purchase.order.create', [
            'supplierId' => $request->integer('supplier') ?: null,
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View
    {
        abort_unless($purchaseOrder->status->isEditable(), 403);

        return view('purchase.order.edit', ['order' => $purchaseOrder]);
    }

    public function detail(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load([
            'items.product.baseUnit', 'items.unit', 'items.taxCode',
            'supplier', 'warehouse', 'creator', 'approver', 'request',
            'receipts', 'invoices',
        ]);

        return view('purchase.order.detail', [
            'order' => $purchaseOrder,
            'approval' => ApprovalRequest::where('document_type', $purchaseOrder->approvalDocumentType())
                ->where('document_id', $purchaseOrder->getKey())
                ->with('steps.approver', 'steps.actor')
                ->latest('id')
                ->first(),
        ]);
    }

    public function print(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['items.product', 'items.unit', 'supplier', 'warehouse', 'branch', 'company']);

        return view('purchase.order.print', ['order' => $purchaseOrder]);
    }

    /**
     * Satu PR bisa dipecah ke beberapa supplier, jadi supplier dipilih saat
     * PO dibuat, bukan saat PR diajukan.
     */
    public function fromRequest(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
        ]);

        try {
            $order = $this->orders->fromRequest($purchaseRequest, (int) $validated['supplier_id']);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('purchase.orders.edit', $order)
            ->with('status', 'Purchase order draft dibuat dari '.$purchaseRequest->number.'.');
    }

    public function submit(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        try {
            $this->orders->submit($purchaseOrder);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Purchase order diajukan.');
    }

    public function approve(PurchaseOrder $purchaseOrder, Request $request): RedirectResponse
    {
        try {
            $this->orders->approve($purchaseOrder, $request->string('note')->toString() ?: null);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Purchase order disetujui.');
    }

    public function reject(PurchaseOrder $purchaseOrder, Request $request): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $this->orders->reject($purchaseOrder, $validated['reason']);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Purchase order ditolak.');
    }

    public function close(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        try {
            $this->orders->close($purchaseOrder);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Purchase order ditutup; sisa barang dianggap tidak dikirim.');
    }

    public function hapus(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        try {
            $this->orders->cancel($purchaseOrder);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Purchase order dibatalkan.');
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            DocumentStatus::Draft->value => DocumentStatus::Draft->label(),
            DocumentStatus::Submitted->value => 'Menunggu Approval',
            DocumentStatus::Approved->value => DocumentStatus::Approved->label(),
            DocumentStatus::PartiallyProcessed->value => 'Diterima Sebagian',
            DocumentStatus::Completed->value => 'Selesai',
            DocumentStatus::Rejected->value => DocumentStatus::Rejected->label(),
            DocumentStatus::Cancelled->value => DocumentStatus::Cancelled->label(),
        ];
    }
}
