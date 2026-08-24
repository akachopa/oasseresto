<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Controllers;

use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Scopes\LocationScope;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Purchase\Models\PurchaseRequest;
use App\Modules\Purchase\Services\PurchaseRequestService;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PurchaseRequestController
{
    public function __construct(
        private readonly PurchaseRequestService $requests,
        private readonly LocationScope $location,
    ) {}

    public function index(): View
    {
        return view('purchase.request.index', [
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
            'priorities' => PurchaseRequest::priorities(),
            'statuses' => self::statusOptions(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = PurchaseRequest::query()
            ->join('warehouses', 'warehouses.id', '=', 'purchase_requests.warehouse_id')
            ->select(['purchase_requests.*', 'warehouses.name as warehouse_name']);

        $this->location->applyBranch($query, 'purchase_requests.branch_id', includeNull: true);

        return response()->json(
            ServerTable::of($query)
                ->searchable(['purchase_requests.number', 'purchase_requests.note', 'warehouses.name'])
                ->orderable([
                    null, 'purchase_requests.number', 'purchase_requests.request_date',
                    'warehouses.name', 'purchase_requests.needed_date',
                    'purchase_requests.estimated_total', 'purchase_requests.status', null,
                ])
                ->filter('warehouse', fn ($q, $value) => $q->where('purchase_requests.warehouse_id', $value))
                ->filter('priority', fn ($q, $value) => $q->where('purchase_requests.priority', $value))
                ->filter('status', fn ($q, $value) => $q->where('purchase_requests.status', $value))
                ->transform(fn (PurchaseRequest $item) => [
                    'number' => '<a class="font-mono text-xs hover:text-brand-600" href="'
                        .route('purchase.requests.detail', $item).'">'.e($item->number).'</a>',
                    'date' => $item->request_date->format('d/m/Y'),
                    'warehouse' => e((string) $item->warehouse_name),
                    'needed' => $item->needed_date?->format('d/m/Y') ?? '-',
                    'estimate' => Money::rupiah($item->estimated_total),
                    'status' => view('components.status-badge', ['status' => $item->status])->render(),
                    'aksi' => view('components.row-actions', [
                        'detail' => route('purchase.requests.detail', $item),
                        'edit' => $item->status->isEditable() ? route('purchase.requests.edit', $item) : null,
                        'delete' => $item->status->isEditable() ? route('purchase.requests.hapus', $item) : null,
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(): View
    {
        return view('purchase.request.create');
    }

    public function edit(PurchaseRequest $purchaseRequest): View
    {
        abort_unless($purchaseRequest->status->isEditable(), 403);

        return view('purchase.request.edit', ['request' => $purchaseRequest]);
    }

    public function detail(PurchaseRequest $purchaseRequest): View
    {
        $purchaseRequest->load([
            'items.product.baseUnit', 'items.unit', 'items.suggestedSupplier',
            'warehouse', 'creator', 'approver', 'orders.supplier',
        ]);

        return view('purchase.request.detail', [
            'request' => $purchaseRequest,
            'suppliers' => Supplier::active()->orderBy('name')->pluck('name', 'id'),
            'approval' => ApprovalRequest::where('document_type', $purchaseRequest->approvalDocumentType())
                ->where('document_id', $purchaseRequest->getKey())
                ->with('steps.approver', 'steps.actor')
                ->latest('id')
                ->first(),
        ]);
    }

    public function submit(PurchaseRequest $purchaseRequest): RedirectResponse
    {
        try {
            $this->requests->submit($purchaseRequest);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Purchase request diajukan.');
    }

    public function approve(PurchaseRequest $purchaseRequest, Request $request): RedirectResponse
    {
        try {
            $this->requests->approve($purchaseRequest, $request->string('note')->toString() ?: null);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Purchase request disetujui.');
    }

    public function reject(PurchaseRequest $purchaseRequest, Request $request): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $this->requests->reject($purchaseRequest, $validated['reason']);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Purchase request ditolak.');
    }

    public function hapus(PurchaseRequest $purchaseRequest): RedirectResponse
    {
        try {
            $this->requests->cancel($purchaseRequest);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Purchase request dibatalkan.');
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            DocumentStatus::Draft->value => DocumentStatus::Draft->label(),
            DocumentStatus::Submitted->value => DocumentStatus::Submitted->label(),
            DocumentStatus::Approved->value => DocumentStatus::Approved->label(),
            DocumentStatus::PartiallyProcessed->value => 'Sebagian Dipesan',
            DocumentStatus::Completed->value => 'Selesai Dipesan',
            DocumentStatus::Rejected->value => DocumentStatus::Rejected->label(),
            DocumentStatus::Cancelled->value => DocumentStatus::Cancelled->label(),
        ];
    }
}
