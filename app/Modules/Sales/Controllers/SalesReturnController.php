<?php

declare(strict_types=1);

namespace App\Modules\Sales\Controllers;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Scopes\LocationScope;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Customer\Models\Customer;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesReturn;
use App\Modules\Sales\Services\SalesReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class SalesReturnController
{
    public function __construct(
        private readonly SalesReturnService $returns,
        private readonly LocationScope $location,
    ) {}

    public function index(): View
    {
        return view('sales.return.index', [
            'customers' => Customer::active()->orderBy('name')->pluck('name', 'id'),
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
            'reasons' => SalesReturn::reasons(),
            'statuses' => self::statusOptions(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = SalesReturn::query()
            ->join('customers', 'customers.id', '=', 'sales_returns.customer_id')
            ->join('warehouses', 'warehouses.id', '=', 'sales_returns.warehouse_id')
            ->select([
                'sales_returns.*',
                'customers.name as customer_name',
                'warehouses.name as warehouse_name',
            ]);

        $this->location->applyBranch($query, 'sales_returns.branch_id', includeNull: true);

        return response()->json(
            ServerTable::of($query)
                ->searchable(['sales_returns.number', 'customers.name'])
                ->orderable([
                    null, 'sales_returns.number', 'sales_returns.return_date',
                    'customers.name', 'warehouses.name', 'sales_returns.reason',
                    'sales_returns.total', 'sales_returns.status', null,
                ])
                ->filter('customer', fn ($q, $value) => $q->where('sales_returns.customer_id', $value))
                ->filter('warehouse', fn ($q, $value) => $q->where('sales_returns.warehouse_id', $value))
                ->filter('reason', fn ($q, $value) => $q->where('sales_returns.reason', $value))
                ->filter('status', fn ($q, $value) => $q->where('sales_returns.status', $value))
                ->transform(fn (SalesReturn $return) => [
                    'number' => '<a class="font-mono text-xs hover:text-brand-600" href="'
                        .route('sales.returns.detail', $return).'">'.e($return->number).'</a>',
                    'date' => $return->return_date->format('d/m/Y'),
                    'customer' => e((string) $return->customer_name),
                    'warehouse' => e((string) $return->warehouse_name),
                    'reason' => e($return->reasonLabel())
                        .($return->restock ? '' : ' <span class="badge-warning ml-1">tidak restock</span>'),
                    'total' => Money::rupiah($return->total),
                    'status' => view('components.status-badge', ['status' => $return->status])->render(),
                    'aksi' => view('components.row-actions', [
                        'detail' => route('sales.returns.detail', $return),
                        'edit' => $return->status->isEditable() ? route('sales.returns.edit', $return) : null,
                        'delete' => $return->status->isEditable() ? route('sales.returns.hapus', $return) : null,
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(Request $request): View
    {
        $invoiceId = $request->integer('invoice') ?: null;

        if ($invoiceId !== null) {
            $invoice = SalesInvoice::findOrFail($invoiceId);

            abort_unless($invoice->isPosted(), 403);
        }

        return view('sales.return.create', ['invoiceId' => $invoiceId]);
    }

    public function edit(SalesReturn $salesReturn): View
    {
        abort_unless($salesReturn->status->isEditable(), 403);

        return view('sales.return.edit', ['return' => $salesReturn]);
    }

    public function detail(SalesReturn $salesReturn): View
    {
        $salesReturn->load([
            'items.product.baseUnit', 'items.unit', 'items.batch',
            'customer', 'warehouse', 'invoice', 'creator',
        ]);

        return view('sales.return.detail', ['return' => $salesReturn]);
    }

    public function post(SalesReturn $salesReturn): RedirectResponse
    {
        try {
            $this->returns->post($salesReturn);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Retur diposting; stok dan piutang sudah disesuaikan.');
    }

    public function hapus(SalesReturn $salesReturn): RedirectResponse
    {
        try {
            $this->returns->cancel($salesReturn);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Retur penjualan dibatalkan.');
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
