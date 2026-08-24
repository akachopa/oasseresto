<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Finance\Models\CashAccount;
use App\Modules\Finance\Models\Payment;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pembayaran ke supplier (PLAN 32). Kas hanya boleh berkurang untuk hutang
 * yang benar-benar ada, jadi pembayaran wajib dialokasikan sebelum diposting.
 */
class PaymentService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly PaymentAllocationService $allocations,
        private readonly CashService $cash,
        private readonly ScopeManager $scope,
    ) {}

    /**
     * @param  array<int, array{target_id: int, amount: float, discount_amount?: ?float, note?: ?string}>  $lines
     */
    public function save(?Payment $payment, array $attributes, array $lines = []): Payment
    {
        return DB::transaction(function () use ($payment, $attributes, $lines): Payment {
            if ($payment !== null && ! $payment->status->isEditable()) {
                throw new RuntimeException('Pembayaran yang sudah diposting tidak bisa diubah.');
            }

            $supplier = Supplier::findOrFail($attributes['supplier_id']);
            $account = CashAccount::findOrFail($attributes['cash_account_id']);
            $date = Carbon::parse($attributes['payment_date']);

            $payment ??= new Payment([
                'status' => DocumentStatus::Draft,
                'created_by' => Auth::id(),
            ]);

            $payment->fill([
                'supplier_id' => $supplier->getKey(),
                'cash_account_id' => $account->getKey(),
                'branch_id' => $attributes['branch_id'] ?? $account->branch_id ?? $this->scope->branchId(),
                'payment_date' => $date->toDateString(),
                'method' => $attributes['method'] ?? 'transfer',
                'reference' => $attributes['reference'] ?? null,
                'amount' => round((float) $attributes['amount'], 4),
                'note' => $attributes['note'] ?? null,
            ]);

            $payment->number ??= $this->numbers->next('payment', $payment->branch_id, $date);
            $payment->save();

            $this->allocations->allocatePayment($payment, $lines);

            return $payment->refresh();
        });
    }

    public function post(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment): Payment {
            if ($payment->status !== DocumentStatus::Draft) {
                throw new RuntimeException('Hanya pembayaran draft yang bisa diposting.');
            }

            if ($payment->allocations()->count() === 0) {
                throw new RuntimeException('Alokasikan pembayaran ke minimal satu hutang sebelum diposting.');
            }

            if ($payment->cashAccount->balance + 0.0001 < $payment->amount) {
                throw new RuntimeException('Saldo kas/bank tidak cukup untuk pembayaran ini.');
            }

            $this->cash->record(
                account: $payment->cashAccount,
                direction: 'out',
                amount: $payment->amount,
                category: 'payment',
                description: 'Pembayaran ke '.($payment->supplier?->name ?? '-'),
                documentType: 'payment',
                documentId: (int) $payment->getKey(),
                documentNumber: $payment->number,
                date: $payment->payment_date,
            );

            $payment->status = DocumentStatus::Posted;
            $payment->posted_by = Auth::id();
            $payment->posted_at = now();
            $payment->save();

            return $payment->refresh();
        });
    }

    public function cancel(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment): Payment {
            if ($payment->status === DocumentStatus::Cancelled) {
                throw new RuntimeException('Pembayaran ini sudah dibatalkan.');
            }

            if ($payment->isPosted()) {
                $this->cash->reverseDocument('payment', (int) $payment->getKey(), 'Pembatalan '.$payment->number);
            }

            $this->allocations->clear('payment', (int) $payment->getKey());

            $payment->allocated_amount = 0;
            $payment->discount_amount = 0;
            $payment->status = DocumentStatus::Cancelled;
            $payment->save();

            return $payment;
        });
    }
}
