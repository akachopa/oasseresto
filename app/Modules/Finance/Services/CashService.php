<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\CashAccount;
use App\Modules\Finance\Models\CashTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Satu-satunya pintu perubahan saldo kas dan bank (PLAN 31). Setiap mutasi
 * menulis satu baris buku kas beserta saldo setelahnya, sehingga buku kas
 * bisa dibaca berurutan tanpa menghitung ulang dari nol.
 */
class CashService
{
    public function record(
        CashAccount $account,
        string $direction,
        float $amount,
        string $category = 'other',
        ?string $description = null,
        ?string $documentType = null,
        ?int $documentId = null,
        ?string $documentNumber = null,
        ?Carbon $date = null,
    ): CashTransaction {
        if ($amount <= 0) {
            throw new RuntimeException('Nilai mutasi kas harus lebih besar dari nol.');
        }

        if (! in_array($direction, ['in', 'out'], true)) {
            throw new RuntimeException('Arah mutasi kas tidak dikenal.');
        }

        return DB::transaction(function () use (
            $account, $direction, $amount, $category, $description,
            $documentType, $documentId, $documentNumber, $date
        ): CashTransaction {
            $locked = CashAccount::withoutGlobalScopes()
                ->whereKey($account->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $signed = $direction === 'in' ? $amount : -$amount;
            $balance = round($locked->balance + $signed, 4);

            $locked->balance = $balance;
            $locked->save();

            return CashTransaction::create([
                'company_id' => $locked->company_id,
                'branch_id' => $locked->branch_id,
                'cash_account_id' => $locked->getKey(),
                'transaction_date' => ($date ?? now())->toDateString(),
                'direction' => $direction,
                'category' => $category,
                'document_type' => $documentType,
                'document_id' => $documentId,
                'document_number' => $documentNumber,
                'amount' => round($amount, 4),
                'balance_after' => $balance,
                'description' => $description,
                'created_by' => Auth::id(),
            ]);
        });
    }

    /**
     * Batalkan mutasi sebuah dokumen. Dipakai saat dokumen kas dibatalkan
     * setelah diposting supaya saldo kembali benar tanpa menghapus jejak.
     */
    public function reverseDocument(string $documentType, int $documentId, ?string $description = null): void
    {
        CashTransaction::where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->get()
            ->each(function (CashTransaction $transaction) use ($description): void {
                $account = $transaction->cashAccount;

                if ($account === null) {
                    return;
                }

                $this->record(
                    account: $account,
                    direction: $transaction->isInbound() ? 'out' : 'in',
                    amount: $transaction->amount,
                    category: $transaction->category,
                    description: $description ?? 'Pembalikan '.$transaction->document_number,
                    documentType: $transaction->document_type.'_reversal',
                    documentId: $transaction->document_id,
                    documentNumber: $transaction->document_number,
                );
            });
    }

    /**
     * Pindah dana antar kas/bank selalu dua mutasi agar buku kas kedua akun
     * tetap lengkap.
     *
     * @return array<int, CashTransaction>
     */
    public function transfer(CashAccount $from, CashAccount $to, float $amount, ?string $note = null, ?Carbon $date = null): array
    {
        if ($from->is($to)) {
            throw new RuntimeException('Akun tujuan harus berbeda dari akun asal.');
        }

        return DB::transaction(fn (): array => [
            $this->record($from, 'out', $amount, 'transfer_out', $note ?? 'Transfer ke '.$to->name, date: $date),
            $this->record($to, 'in', $amount, 'transfer_in', $note ?? 'Transfer dari '.$from->name, date: $date),
        ]);
    }

    /**
     * Saldo per tanggal untuk laporan posisi kas. Saldo awal ikut tercatat
     * sebagai mutasi, jadi cukup menjumlahkan buku kas sampai tanggal itu.
     */
    public function balanceAt(CashAccount $account, Carbon $date): float
    {
        return round((float) CashTransaction::where('cash_account_id', $account->getKey())
            ->whereDate('transaction_date', '<=', $date->toDateString())
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0) as total")
            ->value('total'), 4);
    }
}
