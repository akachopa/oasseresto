<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Customer\Models\Customer;
use App\Modules\Finance\Models\CashAccount;
use App\Modules\Finance\Models\Receipt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Penerimaan pembayaran customer (PLAN 31). Kas baru bertambah saat dokumen
 * diposting; giro dan cek menunggu tanggal cair sehingga kas tidak dihitung
 * lebih awal.
 */
class ReceiptService
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
    public function save(?Receipt $receipt, array $attributes, array $lines = []): Receipt
    {
        return DB::transaction(function () use ($receipt, $attributes, $lines): Receipt {
            if ($receipt !== null && ! $receipt->status->isEditable()) {
                throw new RuntimeException('Penerimaan yang sudah diposting tidak bisa diubah.');
            }

            $customer = Customer::findOrFail($attributes['customer_id']);
            $account = CashAccount::findOrFail($attributes['cash_account_id']);
            $date = Carbon::parse($attributes['receipt_date']);

            $receipt ??= new Receipt([
                'status' => DocumentStatus::Draft,
                'created_by' => Auth::id(),
            ]);

            $receipt->fill([
                'customer_id' => $customer->getKey(),
                'cash_account_id' => $account->getKey(),
                'branch_id' => $attributes['branch_id'] ?? $account->branch_id ?? $this->scope->branchId(),
                'receipt_date' => $date->toDateString(),
                'method' => $attributes['method'] ?? 'cash',
                'reference' => $attributes['reference'] ?? null,
                'cleared_date' => $attributes['cleared_date'] ?? null,
                'amount' => round((float) $attributes['amount'], 4),
                'collected_by' => $attributes['collected_by'] ?? Auth::id(),
                'note' => $attributes['note'] ?? null,
            ]);

            $receipt->number ??= $this->numbers->next('receipt', $receipt->branch_id, $date);
            $receipt->save();

            $this->allocations->allocateReceipt($receipt, $lines);

            return $receipt->refresh();
        });
    }

    /**
     * Posting penerimaan: kas bertambah dan piutang yang dialokasikan berkurang.
     */
    public function post(Receipt $receipt): Receipt
    {
        return DB::transaction(function () use ($receipt): Receipt {
            if ($receipt->status !== DocumentStatus::Draft) {
                throw new RuntimeException('Hanya penerimaan draft yang bisa diposting.');
            }

            if ($receipt->allocations()->count() === 0) {
                throw new RuntimeException('Alokasikan penerimaan ke minimal satu piutang sebelum diposting.');
            }

            $this->cash->record(
                account: $receipt->cashAccount,
                direction: 'in',
                amount: $receipt->amount,
                category: 'receipt',
                description: 'Penerimaan dari '.($receipt->customer?->name ?? '-'),
                documentType: 'receipt',
                documentId: (int) $receipt->getKey(),
                documentNumber: $receipt->number,
                date: $receipt->receipt_date,
            );

            $receipt->status = DocumentStatus::Posted;
            $receipt->posted_by = Auth::id();
            $receipt->posted_at = now();
            $receipt->save();

            return $receipt->refresh();
        });
    }

    /**
     * Batalkan penerimaan. Bila sudah diposting, kas dibalik dan alokasi
     * dilepas supaya piutang kembali terbuka.
     */
    public function cancel(Receipt $receipt): Receipt
    {
        return DB::transaction(function () use ($receipt): Receipt {
            if ($receipt->status === DocumentStatus::Cancelled) {
                throw new RuntimeException('Penerimaan ini sudah dibatalkan.');
            }

            if ($receipt->isPosted()) {
                $this->cash->reverseDocument('receipt', (int) $receipt->getKey(), 'Pembatalan '.$receipt->number);
            }

            $this->allocations->clear('receipt', (int) $receipt->getKey());

            $receipt->allocated_amount = 0;
            $receipt->discount_amount = 0;
            $receipt->status = DocumentStatus::Cancelled;
            $receipt->save();

            return $receipt;
        });
    }

    /**
     * Tandai giro atau cek sudah cair. Dokumen baru dianggap kas riil setelah
     * tanggal cair terisi (PLAN 31).
     */
    public function markCleared(Receipt $receipt, ?Carbon $date = null): Receipt
    {
        if (! $receipt->requiresClearing()) {
            throw new RuntimeException('Metode pembayaran ini tidak perlu proses pencairan.');
        }

        $receipt->cleared_date = ($date ?? now())->toDateString();
        $receipt->save();

        return $receipt;
    }
}
