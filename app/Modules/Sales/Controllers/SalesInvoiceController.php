<?php

declare(strict_types=1);

namespace App\Modules\Sales\Controllers;

use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Scopes\LocationScope;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Customer\Models\Customer;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Services\SalesInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class SalesInvoiceController
{
    public function __construct(
        private readonly SalesInvoiceService $invoices,
        private readonly LocationScope $location,
    ) {}

    public function index(): View
    {
        return view('sales.invoice.index', [
            'customers' => Customer::active()->orderBy('name')->pluck('name', 'id'),
            'statuses' => self::statusOptions(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = SalesInvoice::query()
            ->join('customers', 'customers.id', '=', 'sales_invoices.customer_id')
            ->select(['sales_invoices.*', 'customers.name as customer_name']);

        $this->location->applyBranch($query, 'sales_invoices.branch_id', includeNull: true);

        return response()->json(
            ServerTable::of($query)
                ->searchable(['sales_invoices.number', 'customers.name', 'customers.code'])
                ->orderable([
                    null, 'sales_invoices.number', 'sales_invoices.invoice_date',
                    'customers.name', 'sales_invoices.due_date', 'sales_invoices.total',
                    'sales_invoices.outstanding_amount', 'sales_invoices.status', null,
                ])
                ->filter('customer', fn ($q, $value) => $q->where('sales_invoices.customer_id', $value))
                ->filter('status', fn ($q, $value) => $q->where('sales_invoices.status', $value))
                ->filter('source', fn ($q, $value) => $q->where('sales_invoices.source', $value))
                ->filter('overdue', fn ($q) => $q
                    ->where('sales_invoices.outstanding_amount', '>', 0)
                    ->whereDate('sales_invoices.due_date', '<', now()->toDateString()))
                ->transform(fn (SalesInvoice $invoice) => [
                    'number' => '<a class="font-mono text-xs hover:text-brand-600" href="'
                        .route('sales.invoices.detail', $invoice).'">'.e($invoice->number).'</a>'
                        .($invoice->source === 'pos' ? ' <span class="badge-info ml-1">POS</span>' : ''),
                    'date' => $invoice->invoice_date->format('d/m/Y'),
                    'customer' => e((string) $invoice->customer_name),
                    'due' => $invoice->due_date->format('d/m/Y')
                        .($invoice->isOverdue()
                            ? ' <span class="badge-danger ml-1">'.$invoice->daysOverdue().' hari</span>'
                            : ''),
                    'total' => Money::rupiah($invoice->total),
                    'outstanding' => Money::rupiah($invoice->outstanding_amount),
                    'status' => view('components.status-badge', ['status' => $invoice->status])->render(),
                    'aksi' => view('components.row-actions', [
                        'detail' => route('sales.invoices.detail', $invoice),
                        'edit' => $invoice->status->isEditable() ? route('sales.invoices.edit', $invoice) : null,
                        'delete' => $invoice->status->isEditable() ? route('sales.invoices.hapus', $invoice) : null,
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(Request $request): View
    {
        $deliveryId = $request->integer('delivery') ?: null;

        if ($deliveryId !== null) {
            $delivery = Delivery::findOrFail($deliveryId);

            abort_unless($delivery->uninvoicedBaseQuantity() > 0, 403);
        }

        return view('sales.invoice.create', ['deliveryId' => $deliveryId]);
    }

    public function edit(SalesInvoice $salesInvoice): View
    {
        abort_unless($salesInvoice->status->isEditable(), 403);

        return view('sales.invoice.edit', ['invoice' => $salesInvoice]);
    }

    public function detail(SalesInvoice $salesInvoice): View
    {
        $salesInvoice->load([
            'items.product.baseUnit', 'items.unit', 'items.taxCode',
            'customer', 'order', 'delivery', 'salesman', 'creator', 'shift',
        ]);

        return view('sales.invoice.detail', ['invoice' => $salesInvoice]);
    }

    public function print(SalesInvoice $salesInvoice): View
    {
        $salesInvoice->load(['items.product', 'items.unit', 'customer', 'branch', 'company', 'delivery']);

        return view('sales.invoice.print', ['invoice' => $salesInvoice]);
    }

    public function post(SalesInvoice $salesInvoice, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $this->invoices->post($salesInvoice, (float) ($validated['paid_amount'] ?? 0));
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Invoice diposting dan piutang customer terbentuk.');
    }

    public function hapus(SalesInvoice $salesInvoice): RedirectResponse
    {
        try {
            $this->invoices->cancel($salesInvoice);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Invoice penjualan dibatalkan.');
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
