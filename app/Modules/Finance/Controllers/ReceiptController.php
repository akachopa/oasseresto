<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Scopes\LocationScope;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Customer\Models\Customer;
use App\Modules\Finance\Models\Receipt;
use App\Modules\Finance\Services\ReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ReceiptController
{
    public function __construct(
        private readonly ReceiptService $receipts,
        private readonly LocationScope $location,
    ) {}

    public function index(): View
    {
        return view('finance.receipt.index', [
            'customers' => Customer::active()->orderBy('name')->pluck('name', 'id'),
            'methods' => Receipt::methods(),
            'statuses' => self::statusOptions(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Receipt::query()
            ->join('customers', 'customers.id', '=', 'receipts.customer_id')
            ->join('cash_accounts', 'cash_accounts.id', '=', 'receipts.cash_account_id')
            ->select([
                'receipts.*',
                'customers.name as customer_name',
                'cash_accounts.name as account_name',
            ]);

        $this->location->applyBranch($query, 'receipts.branch_id', includeNull: true);

        return response()->json(
            ServerTable::of($query)
                ->searchable(['receipts.number', 'receipts.reference', 'customers.name'])
                ->orderable([
                    null, 'receipts.number', 'receipts.receipt_date',
                    'customers.name', 'cash_accounts.name', 'receipts.method',
                    'receipts.amount', 'receipts.status', null,
                ])
                ->filter('customer', fn ($q, $value) => $q->where('receipts.customer_id', $value))
                ->filter('method', fn ($q, $value) => $q->where('receipts.method', $value))
                ->filter('status', fn ($q, $value) => $q->where('receipts.status', $value))
                ->transform(fn (Receipt $receipt) => [
                    'number' => '<a class="font-mono text-xs hover:text-brand-600" href="'
                        .route('finance.receipts.detail', $receipt).'">'.e($receipt->number).'</a>',
                    'date' => $receipt->receipt_date->format('d/m/Y'),
                    'customer' => e((string) $receipt->customer_name),
                    'account' => e((string) $receipt->account_name),
                    'method' => e($receipt->methodLabel())
                        .($receipt->requiresClearing() && $receipt->cleared_date === null
                            ? ' <span class="badge-warning ml-1">belum cair</span>'
                            : ''),
                    'amount' => Money::rupiah($receipt->amount),
                    'status' => view('components.status-badge', ['status' => $receipt->status])->render(),
                    'aksi' => view('components.row-actions', [
                        'detail' => route('finance.receipts.detail', $receipt),
                        'edit' => $receipt->status->isEditable() ? route('finance.receipts.edit', $receipt) : null,
                        'delete' => $receipt->status !== DocumentStatus::Cancelled
                            ? route('finance.receipts.hapus', $receipt)
                            : null,
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(Request $request): View
    {
        return view('finance.receipt.create', [
            'customerId' => $request->integer('customer') ?: null,
        ]);
    }

    public function edit(Receipt $receipt): View
    {
        abort_unless($receipt->status->isEditable(), 403);

        return view('finance.receipt.edit', ['receipt' => $receipt]);
    }

    public function detail(Receipt $receipt): View
    {
        $receipt->load(['customer', 'cashAccount', 'collector', 'creator', 'allocations']);

        return view('finance.receipt.detail', [
            'receipt' => $receipt,
            'allocations' => $receipt->allocations->map(fn ($allocation) => [
                'model' => $allocation,
                'target' => $allocation->target(),
            ]),
        ]);
    }

    public function post(Receipt $receipt): RedirectResponse
    {
        try {
            $this->receipts->post($receipt);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Penerimaan diposting; kas bertambah dan piutang berkurang.');
    }

    public function clear(Receipt $receipt): RedirectResponse
    {
        try {
            $this->receipts->markCleared($receipt);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Giro/cek ditandai sudah cair.');
    }

    public function hapus(Receipt $receipt): RedirectResponse
    {
        try {
            $this->receipts->cancel($receipt);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Penerimaan dibatalkan dan alokasinya dilepas.');
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
