<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Controllers;

use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Scopes\LocationScope;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Purchase\Models\GoodsReceipt;
use App\Modules\Purchase\Models\PurchaseInvoice;
use App\Modules\Purchase\Services\PurchaseInvoiceService;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PurchaseInvoiceController
{
    public function __construct(
        private readonly PurchaseInvoiceService $invoices,
        private readonly LocationScope $location,
    ) {}

    public function index(): View
    {
        return view('purchase.invoice.index', [
            'suppliers' => Supplier::active()->orderBy('name')->pluck('name', 'id'),
            'statuses' => self::statusOptions(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = PurchaseInvoice::query()
            ->join('suppliers', 'suppliers.id', '=', 'purchase_invoices.supplier_id')
            ->select(['purchase_invoices.*', 'suppliers.name as supplier_name']);

        $this->location->applyBranch($query, 'purchase_invoices.branch_id', includeNull: true);

        return response()->json(
            ServerTable::of($query)
                ->searchable([
                    'purchase_invoices.number', 'purchase_invoices.supplier_invoice_number', 'suppliers.name',
                ])
                ->orderable([
                    null, 'purchase_invoices.number', 'purchase_invoices.invoice_date',
                    'suppliers.name', 'purchase_invoices.due_date', 'purchase_invoices.total',
                    'purchase_invoices.outstanding_amount', 'purchase_invoices.status', null,
                ])
                ->filter('supplier', fn ($q, $value) => $q->where('purchase_invoices.supplier_id', $value))
                ->filter('status', fn ($q, $value) => $q->where('purchase_invoices.status', $value))
                ->filter('overdue', fn ($q) => $q
                    ->where('purchase_invoices.outstanding_amount', '>', 0)
                    ->whereDate('purchase_invoices.due_date', '<', now()->toDateString()))
                ->transform(fn (PurchaseInvoice $invoice) => [
                    'number' => '<a class="font-mono text-xs hover:text-brand-600" href="'
                        .route('purchase.invoices.detail', $invoice).'">'.e($invoice->number).'</a>'
                        .($invoice->supplier_invoice_number
                            ? '<span class="text-muted block text-xs">'.e($invoice->supplier_invoice_number).'</span>'
                            : ''),
                    'date' => $invoice->invoice_date->format('d/m/Y'),
                    'supplier' => e((string) $invoice->supplier_name),
                    'due' => $invoice->due_date->format('d/m/Y')
                        .($invoice->isOverdue()
                            ? '<span class="badge-danger ml-1">'.$invoice->daysOverdue().' hari</span>'
                            : ''),
                    'total' => Money::rupiah($invoice->total),
                    'outstanding' => Money::rupiah($invoice->outstanding_amount),
                    'status' => view('components.status-badge', ['status' => $invoice->status])->render(),
                    'aksi' => view('components.row-actions', [
                        'detail' => route('purchase.invoices.detail', $invoice),
                        'edit' => $invoice->status->isEditable() ? route('purchase.invoices.edit', $invoice) : null,
                        'delete' => $invoice->status->isEditable() ? route('purchase.invoices.hapus', $invoice) : null,
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(Request $request): View
    {
        $receiptId = $request->integer('receipt') ?: null;

        if ($receiptId !== null) {
            $receipt = GoodsReceipt::findOrFail($receiptId);

            abort_unless($receipt->isPosted(), 403);
        }

        return view('purchase.invoice.create', ['receiptId' => $receiptId]);
    }

    public function edit(PurchaseInvoice $purchaseInvoice): View
    {
        abort_unless($purchaseInvoice->status->isEditable(), 403);

        return view('purchase.invoice.edit', ['invoice' => $purchaseInvoice]);
    }

    public function detail(PurchaseInvoice $purchaseInvoice): View
    {
        $purchaseInvoice->load([
            'items.product.baseUnit', 'items.unit', 'items.taxCode',
            'supplier', 'order', 'creator',
        ]);

        return view('purchase.invoice.detail', ['invoice' => $purchaseInvoice]);
    }

    public function post(PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        try {
            $this->invoices->post($purchaseInvoice);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Invoice diposting dan hutang supplier terbentuk.');
    }

    public function hapus(PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        try {
            $this->invoices->cancel($purchaseInvoice);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Invoice pembelian dibatalkan.');
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
