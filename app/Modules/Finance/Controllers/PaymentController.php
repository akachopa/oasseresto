<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Scopes\LocationScope;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PaymentController
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly LocationScope $location,
    ) {}

    public function index(): View
    {
        return view('finance.payment.index', [
            'suppliers' => Supplier::active()->orderBy('name')->pluck('name', 'id'),
            'methods' => Payment::methods(),
            'statuses' => self::statusOptions(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Payment::query()
            ->join('suppliers', 'suppliers.id', '=', 'payments.supplier_id')
            ->join('cash_accounts', 'cash_accounts.id', '=', 'payments.cash_account_id')
            ->select([
                'payments.*',
                'suppliers.name as supplier_name',
                'cash_accounts.name as account_name',
            ]);

        $this->location->applyBranch($query, 'payments.branch_id', includeNull: true);

        return response()->json(
            ServerTable::of($query)
                ->searchable(['payments.number', 'payments.reference', 'suppliers.name'])
                ->orderable([
                    null, 'payments.number', 'payments.payment_date',
                    'suppliers.name', 'cash_accounts.name', 'payments.method',
                    'payments.amount', 'payments.status', null,
                ])
                ->filter('supplier', fn ($q, $value) => $q->where('payments.supplier_id', $value))
                ->filter('method', fn ($q, $value) => $q->where('payments.method', $value))
                ->filter('status', fn ($q, $value) => $q->where('payments.status', $value))
                ->transform(fn (Payment $payment) => [
                    'number' => '<a class="font-mono text-xs hover:text-brand-600" href="'
                        .route('finance.payments.detail', $payment).'">'.e($payment->number).'</a>',
                    'date' => $payment->payment_date->format('d/m/Y'),
                    'supplier' => e((string) $payment->supplier_name),
                    'account' => e((string) $payment->account_name),
                    'method' => e($payment->methodLabel()),
                    'amount' => Money::rupiah($payment->amount),
                    'status' => view('components.status-badge', ['status' => $payment->status])->render(),
                    'aksi' => view('components.row-actions', [
                        'detail' => route('finance.payments.detail', $payment),
                        'edit' => $payment->status->isEditable() ? route('finance.payments.edit', $payment) : null,
                        'delete' => $payment->status !== DocumentStatus::Cancelled
                            ? route('finance.payments.hapus', $payment)
                            : null,
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(Request $request): View
    {
        return view('finance.payment.create', [
            'supplierId' => $request->integer('supplier') ?: null,
        ]);
    }

    public function edit(Payment $payment): View
    {
        abort_unless($payment->status->isEditable(), 403);

        return view('finance.payment.edit', ['payment' => $payment]);
    }

    public function detail(Payment $payment): View
    {
        $payment->load(['supplier', 'cashAccount', 'creator', 'allocations']);

        return view('finance.payment.detail', [
            'payment' => $payment,
            'allocations' => $payment->allocations->map(fn ($allocation) => [
                'model' => $allocation,
                'target' => $allocation->target(),
            ]),
        ]);
    }

    public function post(Payment $payment): RedirectResponse
    {
        try {
            $this->payments->post($payment);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Pembayaran diposting; kas berkurang dan hutang menurun.');
    }

    public function hapus(Payment $payment): RedirectResponse
    {
        try {
            $this->payments->cancel($payment);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Pembayaran dibatalkan dan alokasinya dilepas.');
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
