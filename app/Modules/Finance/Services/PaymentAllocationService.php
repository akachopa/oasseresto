<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Customer\Models\Customer;
use App\Modules\Finance\Models\Payable;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentAllocation;
use App\Modules\Finance\Models\Receipt;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Purchase\Models\PurchaseInvoice;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Alokasi pembayaran ke tagihan (PLAN 32). Uang tidak pernah "hilang": nilai
 * yang dialokasikan selalu sama dengan penurunan sisa tagihan, dan status
 * tagihan dihitung ulang dari sisa, bukan disetel manual.
 */
class PaymentAllocationService
{
    /**
     * Alokasikan penerimaan ke sejumlah piutang.
     *
     * @param  array<int, array{target_id: int, amount: float, discount_amount?: ?float, note?: ?string}>  $lines
     */
    public function allocateReceipt(Receipt $receipt, array $lines): Receipt
    {
        return DB::transaction(function () use ($receipt, $lines): Receipt {
            $this->clear('receipt', (int) $receipt->getKey());

            $allocated = 0.0;
            $discountTotal = 0.0;

            foreach ($lines as $line) {
                $amount = round((float) $line['amount'], 4);

                if ($amount <= 0) {
                    continue;
                }

                $receivable = Receivable::lockForUpdate()->findOrFail($line['target_id']);

                if ((int) $receivable->customer_id !== (int) $receipt->customer_id) {
                    throw new RuntimeException('Piutang ini milik customer lain.');
                }

                $discount = round((float) ($line['discount_amount'] ?? 0), 4);
                $settled = round($amount + $discount, 4);

                if ($settled > $receivable->outstanding_amount + 0.0001) {
                    throw new RuntimeException(
                        "Alokasi ke {$receivable->document_number} melebihi sisa piutangnya."
                    );
                }

                PaymentAllocation::create([
                    'company_id' => $receipt->company_id,
                    'payment_type' => 'receipt',
                    'payment_id' => $receipt->getKey(),
                    'target_type' => 'receivable',
                    'target_id' => $receivable->getKey(),
                    'amount' => $amount,
                    'discount_amount' => $discount,
                    'note' => $line['note'] ?? null,
                ]);

                /*
                 * Diskon pelunasan dini mengurangi nilai tagihan, bukan dicatat
                 * sebagai uang masuk, sehingga kas tetap mencerminkan realita.
                 */
                if ($discount > 0) {
                    $receivable->amount = round($receivable->amount - $discount, 4);
                }

                $receivable->paid_amount = round($receivable->paid_amount + $amount, 4);
                $receivable->refreshStatus()->save();

                $this->syncSalesInvoice($receivable);

                $allocated += $amount;
                $discountTotal += $discount;
            }

            if ($allocated > $receipt->amount + 0.0001) {
                throw new RuntimeException('Total alokasi melebihi nilai penerimaan.');
            }

            $receipt->allocated_amount = round($allocated, 4);
            $receipt->discount_amount = round($discountTotal, 4);
            $receipt->save();

            $this->refreshCustomer($receipt->customer);

            return $receipt->refresh();
        });
    }

    /**
     * @param  array<int, array{target_id: int, amount: float, discount_amount?: ?float, note?: ?string}>  $lines
     */
    public function allocatePayment(Payment $payment, array $lines): Payment
    {
        return DB::transaction(function () use ($payment, $lines): Payment {
            $this->clear('payment', (int) $payment->getKey());

            $allocated = 0.0;
            $discountTotal = 0.0;

            foreach ($lines as $line) {
                $amount = round((float) $line['amount'], 4);

                if ($amount <= 0) {
                    continue;
                }

                $payable = Payable::lockForUpdate()->findOrFail($line['target_id']);

                if ((int) $payable->supplier_id !== (int) $payment->supplier_id) {
                    throw new RuntimeException('Hutang ini milik supplier lain.');
                }

                $discount = round((float) ($line['discount_amount'] ?? 0), 4);
                $settled = round($amount + $discount, 4);

                if ($settled > $payable->outstanding_amount + 0.0001) {
                    throw new RuntimeException(
                        "Alokasi ke {$payable->document_number} melebihi sisa hutangnya."
                    );
                }

                PaymentAllocation::create([
                    'company_id' => $payment->company_id,
                    'payment_type' => 'payment',
                    'payment_id' => $payment->getKey(),
                    'target_type' => 'payable',
                    'target_id' => $payable->getKey(),
                    'amount' => $amount,
                    'discount_amount' => $discount,
                    'note' => $line['note'] ?? null,
                ]);

                if ($discount > 0) {
                    $payable->amount = round($payable->amount - $discount, 4);
                }

                $payable->paid_amount = round($payable->paid_amount + $amount, 4);
                $payable->refreshStatus()->save();

                $this->syncPurchaseInvoice($payable);

                $allocated += $amount;
                $discountTotal += $discount;
            }

            if ($allocated > $payment->amount + 0.0001) {
                throw new RuntimeException('Total alokasi melebihi nilai pembayaran.');
            }

            $payment->allocated_amount = round($allocated, 4);
            $payment->discount_amount = round($discountTotal, 4);
            $payment->save();

            $this->refreshSupplier($payment->supplier);

            return $payment->refresh();
        });
    }

    /**
     * Alokasi otomatis tagihan tertua lebih dulu, dipakai saat kasir atau
     * salesman hanya menerima uang tanpa memilih invoice.
     *
     * @return array<int, array{target_id: int, amount: float, discount_amount: float}>
     */
    public function suggestForCustomer(Customer $customer, float $amount, ?Carbon $date = null): array
    {
        return $this->suggest($this->openReceivables($customer), $amount, $date);
    }

    /**
     * @return array<int, array{target_id: int, amount: float, discount_amount: float}>
     */
    public function suggestForSupplier(Supplier $supplier, float $amount, ?Carbon $date = null): array
    {
        return $this->suggest($this->openPayables($supplier), $amount, $date);
    }

    /**
     * @return Collection<int, Receivable>
     */
    public function openReceivables(Customer $customer): Collection
    {
        return Receivable::query()
            ->where('customer_id', $customer->getKey())
            ->outstanding()
            ->orderBy('due_date')
            ->orderBy('invoice_date')
            ->get();
    }

    /**
     * @return Collection<int, Payable>
     */
    public function openPayables(Supplier $supplier): Collection
    {
        return Payable::query()
            ->where('supplier_id', $supplier->getKey())
            ->outstanding()
            ->orderBy('due_date')
            ->orderBy('invoice_date')
            ->get();
    }

    /**
     * Diskon pelunasan dini (PLAN 34): berlaku bila dibayar dalam tenggang
     * yang ditetapkan company dan tagihan belum jatuh tempo.
     */
    public function earlyPaymentDiscount(Model $target, float $amount, ?Carbon $date = null): float
    {
        $percent = (float) config('oasse.credit.early_payment.percent', 0);
        $withinDays = (int) config('oasse.credit.early_payment.within_days', 0);

        if ($percent <= 0 || $withinDays <= 0) {
            return 0.0;
        }

        $date ??= now();
        $invoiceDate = $target->getAttribute('invoice_date');
        $dueDate = $target->getAttribute('due_date');

        if ($invoiceDate === null || $date->gt($invoiceDate->copy()->addDays($withinDays))) {
            return 0.0;
        }

        if ($dueDate !== null && $date->gt($dueDate)) {
            return 0.0;
        }

        return round(min($amount, (float) $target->getAttribute('outstanding_amount')) * $percent / 100, 4);
    }

    /**
     * Hapus alokasi sebuah dokumen dan pulihkan sisa tagihannya. Dipakai saat
     * alokasi disusun ulang atau dokumen dibatalkan.
     */
    public function clear(string $paymentType, int $paymentId): void
    {
        PaymentAllocation::where('payment_type', $paymentType)
            ->where('payment_id', $paymentId)
            ->get()
            ->each(function (PaymentAllocation $allocation): void {
                if ($allocation->target_type === 'receivable') {
                    $receivable = Receivable::find($allocation->target_id);

                    if ($receivable !== null) {
                        $receivable->amount = round($receivable->amount + $allocation->discount_amount, 4);
                        $receivable->paid_amount = max(0, round($receivable->paid_amount - $allocation->amount, 4));
                        $receivable->refreshStatus()->save();

                        $this->syncSalesInvoice($receivable);
                    }
                }

                if ($allocation->target_type === 'payable') {
                    $payable = Payable::find($allocation->target_id);

                    if ($payable !== null) {
                        $payable->amount = round($payable->amount + $allocation->discount_amount, 4);
                        $payable->paid_amount = max(0, round($payable->paid_amount - $allocation->amount, 4));
                        $payable->refreshStatus()->save();

                        $this->syncPurchaseInvoice($payable);
                    }
                }

                $allocation->delete();
            });
    }

    /**
     * @param  Collection<int, Model>  $targets
     * @return array<int, array{target_id: int, amount: float, discount_amount: float}>
     */
    private function suggest(Collection $targets, float $amount, ?Carbon $date): array
    {
        $remaining = round($amount, 4);
        $lines = [];

        foreach ($targets as $target) {
            if ($remaining <= 0) {
                break;
            }

            $discount = $this->earlyPaymentDiscount($target, $remaining, $date);
            $outstanding = (float) $target->getAttribute('outstanding_amount');
            $payable = round(max(0, $outstanding - $discount), 4);
            $taken = min($payable, $remaining);

            if ($taken <= 0) {
                continue;
            }

            $lines[] = [
                'target_id' => (int) $target->getKey(),
                'amount' => $taken,
                'discount_amount' => round($taken >= $payable ? $discount : 0, 4),
            ];

            $remaining = round($remaining - $taken, 4);
        }

        return $lines;
    }

    private function syncSalesInvoice(Receivable $receivable): void
    {
        if ($receivable->document_type !== 'sales_invoice') {
            return;
        }

        $invoice = SalesInvoice::find($receivable->document_id);

        if ($invoice === null) {
            return;
        }

        $invoice->paid_amount = $receivable->paid_amount;
        $invoice->outstanding_amount = $receivable->outstanding_amount;
        $invoice->save();
    }

    private function syncPurchaseInvoice(Payable $payable): void
    {
        if ($payable->document_type !== 'purchase_invoice') {
            return;
        }

        $invoice = PurchaseInvoice::find($payable->document_id);

        if ($invoice === null) {
            return;
        }

        $invoice->paid_amount = $payable->paid_amount;
        $invoice->outstanding_amount = $payable->outstanding_amount;
        $invoice->save();
    }

    private function refreshCustomer(?Customer $customer): void
    {
        if ($customer === null) {
            return;
        }

        $rows = Receivable::where('customer_id', $customer->getKey())->outstanding()->get();

        $customer->outstanding_amount = round((float) $rows->sum('outstanding_amount'), 4);
        $customer->overdue_amount = round(
            (float) $rows->filter(fn (Receivable $row) => $row->isOverdue())->sum('outstanding_amount'),
            4,
        );
        $customer->saveQuietly();
    }

    private function refreshSupplier(?Supplier $supplier): void
    {
        if ($supplier === null) {
            return;
        }

        $supplier->outstanding_amount = round(
            (float) Payable::where('supplier_id', $supplier->getKey())->outstanding()->sum('outstanding_amount'),
            4,
        );
        $supplier->saveQuietly();
    }
}
