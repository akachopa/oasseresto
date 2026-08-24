<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Controllers;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Scopes\LocationScope;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Purchase\Models\PurchaseInvoice;
use App\Modules\Purchase\Models\PurchaseReturn;
use App\Modules\Purchase\Services\PurchaseReturnService;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PurchaseReturnController
{
    public function __construct(
        private readonly PurchaseReturnService $returns,
        private readonly LocationScope $location,
    ) {}

    public function index(): View
    {
        return view('purchase.return.index', [
            'suppliers' => Supplier::active()->orderBy('name')->pluck('name', 'id'),
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
            'reasons' => PurchaseReturn::reasons(),
            'statuses' => self::statusOptions(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = PurchaseReturn::query()
            ->join('suppliers', 'suppliers.id', '=', 'purchase_returns.supplier_id')
            ->join('warehouses', 'warehouses.id', '=', 'purchase_returns.warehouse_id')
            ->select([
                'purchase_returns.*',
                'suppliers.name as supplier_name',
                'warehouses.name as warehouse_name',
            ]);

        $this->location->applyBranch($query, 'purchase_returns.branch_id', includeNull: true);

        return response()->json(
            ServerTable::of($query)
                ->searchable(['purchase_returns.number', 'suppliers.name'])
                ->orderable([
                    null, 'purchase_returns.number', 'purchase_returns.return_date',
                    'suppliers.name', 'warehouses.name', 'purchase_returns.reason',
                    'purchase_returns.total', 'purchase_returns.status', null,
                ])
                ->filter('supplier', fn ($q, $value) => $q->where('purchase_returns.supplier_id', $value))
                ->filter('warehouse', fn ($q, $value) => $q->where('purchase_returns.warehouse_id', $value))
                ->filter('reason', fn ($q, $value) => $q->where('purchase_returns.reason', $value))
                ->filter('status', fn ($q, $value) => $q->where('purchase_returns.status', $value))
                ->transform(fn (PurchaseReturn $return) => [
                    'number' => '<a class="font-mono text-xs hover:text-brand-600" href="'
                        .route('purchase.returns.detail', $return).'">'.e($return->number).'</a>',
                    'date' => $return->return_date->format('d/m/Y'),
                    'supplier' => e((string) $return->supplier_name),
                    'warehouse' => e((string) $return->warehouse_name),
                    'reason' => e($return->reasonLabel()),
                    'total' => Money::rupiah($return->total),
                    'status' => view('components.status-badge', ['status' => $return->status])->render(),
                    'aksi' => view('components.row-actions', [
                        'detail' => route('purchase.returns.detail', $return),
                        'edit' => $return->status->isEditable() ? route('purchase.returns.edit', $return) : null,
                        'delete' => $return->status->isEditable() ? route('purchase.returns.hapus', $return) : null,
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(Request $request): View
    {
        $invoiceId = $request->integer('invoice') ?: null;

        if ($invoiceId !== null) {
            $invoice = PurchaseInvoice::findOrFail($invoiceId);

            abort_unless($invoice->isPosted(), 403);
        }

        return view('purchase.return.create', ['invoiceId' => $invoiceId]);
    }

    public function edit(PurchaseReturn $purchaseReturn): View
    {
        abort_unless($purchaseReturn->status->isEditable(), 403);

        return view('purchase.return.edit', ['return' => $purchaseReturn]);
    }

    public function detail(PurchaseReturn $purchaseReturn): View
    {
        $purchaseReturn->load([
            'items.product.baseUnit', 'items.unit', 'items.batch',
            'supplier', 'warehouse', 'invoice', 'creator',
        ]);

        return view('purchase.return.detail', ['return' => $purchaseReturn]);
    }

    public function post(PurchaseReturn $purchaseReturn): RedirectResponse
    {
        try {
            $this->returns->post($purchaseReturn);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Retur diposting; stok keluar dan hutang disesuaikan.');
    }

    public function hapus(PurchaseReturn $purchaseReturn): RedirectResponse
    {
        try {
            $this->returns->cancel($purchaseReturn);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Retur pembelian dibatalkan.');
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
