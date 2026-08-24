<?php

declare(strict_types=1);

namespace App\Modules\Sales\Controllers;

use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Scopes\LocationScope;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Customer\Models\Customer;
use App\Modules\Sales\Models\Quotation;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Services\CreditControlService;
use App\Modules\Sales\Services\SalesOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class SalesOrderController
{
    public function __construct(
        private readonly SalesOrderService $orders,
        private readonly CreditControlService $credit,
        private readonly LocationScope $location,
    ) {}

    public function index(): View
    {
        return view('sales.order.index', [
            'customers' => Customer::active()->orderBy('name')->pluck('name', 'id'),
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
            'statuses' => self::statusOptions(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = SalesOrder::query()
            ->join('customers', 'customers.id', '=', 'sales_orders.customer_id')
            ->join('warehouses', 'warehouses.id', '=', 'sales_orders.warehouse_id')
            ->select([
                'sales_orders.*',
                'customers.name as customer_name',
                'warehouses.name as warehouse_name',
            ]);

        $this->location->applyBranch($query, 'sales_orders.branch_id', includeNull: true);

        return response()->json(
            ServerTable::of($query)
                ->searchable(['sales_orders.number', 'customers.name', 'customers.code'])
                ->orderable([
                    null, 'sales_orders.number', 'sales_orders.order_date',
                    'customers.name', 'warehouses.name', 'sales_orders.total',
                    'sales_orders.credit_status', 'sales_orders.status', null,
                ])
                ->filter('customer', fn ($q, $value) => $q->where('sales_orders.customer_id', $value))
                ->filter('warehouse', fn ($q, $value) => $q->where('sales_orders.warehouse_id', $value))
                ->filter('status', fn ($q, $value) => $q->where('sales_orders.status', $value))
                ->filter('outstanding', fn ($q) => $q->whereIn('sales_orders.status', [
                    DocumentStatus::Approved->value,
                    DocumentStatus::PartiallyProcessed->value,
                ]))
                ->transform(fn (SalesOrder $order) => [
                    'number' => '<a class="font-mono text-xs hover:text-brand-600" href="'
                        .route('sales.orders.detail', $order).'">'.e($order->number).'</a>',
                    'date' => $order->order_date->format('d/m/Y'),
                    'customer' => e((string) $order->customer_name),
                    'warehouse' => e((string) $order->warehouse_name),
                    'total' => Money::rupiah($order->total),
                    'credit' => self::creditBadge($order),
                    'status' => view('components.status-badge', ['status' => $order->status])->render(),
                    'aksi' => view('components.row-actions', [
                        'detail' => route('sales.orders.detail', $order),
                        'edit' => $order->status->isEditable() ? route('sales.orders.edit', $order) : null,
                        'delete' => $order->status->isEditable() ? route('sales.orders.hapus', $order) : null,
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(Request $request): View
    {
        return view('sales.order.create', [
            'customerId' => $request->integer('customer') ?: null,
        ]);
    }

    public function edit(SalesOrder $salesOrder): View
    {
        abort_unless($salesOrder->status->isEditable(), 403);

        return view('sales.order.edit', ['order' => $salesOrder]);
    }

    public function detail(SalesOrder $salesOrder): View
    {
        $salesOrder->load([
            'items.product.baseUnit', 'items.unit', 'items.taxCode',
            'customer.creditProfile', 'warehouse', 'salesman', 'creator', 'approver',
            'quotation', 'deliveries', 'invoices',
        ]);

        return view('sales.order.detail', [
            'order' => $salesOrder,
            'credit' => $this->credit->evaluate(
                $salesOrder->customer,
                (float) $salesOrder->total,
                $salesOrder->payment_term,
                $salesOrder->getKey(),
            ),
            'approval' => ApprovalRequest::where('document_type', $salesOrder->approvalDocumentType())
                ->where('document_id', $salesOrder->getKey())
                ->with('steps.approver', 'steps.actor')
                ->latest('id')
                ->first(),
        ]);
    }

    public function fromQuotation(Quotation $quotation): RedirectResponse
    {
        try {
            $order = $this->orders->fromQuotation($quotation);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('sales.orders.edit', $order)
            ->with('status', 'Sales order draft dibuat dari '.$quotation->number.'.');
    }

    public function submit(SalesOrder $salesOrder): RedirectResponse
    {
        try {
            $this->orders->submit($salesOrder);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Sales order diajukan.');
    }

    public function approve(SalesOrder $salesOrder, Request $request): RedirectResponse
    {
        try {
            $this->orders->approve($salesOrder, $request->string('note')->toString() ?: null);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Sales order disetujui dan stok direservasi.');
    }

    public function reject(SalesOrder $salesOrder, Request $request): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $this->orders->reject($salesOrder, $validated['reason']);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Sales order ditolak.');
    }

    public function close(SalesOrder $salesOrder): RedirectResponse
    {
        try {
            $this->orders->close($salesOrder);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Sales order ditutup dan reservasi stok dilepas.');
    }

    public function hapus(SalesOrder $salesOrder): RedirectResponse
    {
        try {
            $this->orders->cancel($salesOrder);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Sales order dibatalkan.');
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
            DocumentStatus::PartiallyProcessed->value => 'Terkirim Sebagian',
            DocumentStatus::Completed->value => 'Selesai',
            DocumentStatus::Rejected->value => DocumentStatus::Rejected->label(),
            DocumentStatus::Cancelled->value => DocumentStatus::Cancelled->label(),
        ];
    }

    private static function creditBadge(SalesOrder $order): string
    {
        return match ($order->credit_status) {
            'blocked' => '<span class="badge-danger">Diblokir</span>',
            'approval' => '<span class="badge-warning">Approval</span>',
            'warning' => '<span class="badge-warning">Peringatan</span>',
            default => '<span class="badge-success">Aman</span>',
        };
    }
}
