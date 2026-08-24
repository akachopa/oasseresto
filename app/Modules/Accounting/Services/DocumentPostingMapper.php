<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\DTO\CashTransferPayload;
use App\Modules\Accounting\DTO\JournalDraft;
use App\Modules\Company\Models\Company;
use App\Modules\Finance\Models\CashTransaction;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\Receipt;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockOpname;
use App\Modules\Purchase\Models\GoodsReceipt;
use App\Modules\Purchase\Models\PurchaseInvoice;
use App\Modules\Purchase\Models\PurchaseReturn;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesReturn;
use Illuminate\Support\Carbon;

class DocumentPostingMapper
{
    public function __construct(protected AccountResolver $accounts) {}

    /**
     * @return list<JournalDraft>
     */
    public function draftsFor(string $documentType, mixed $document): array
    {
        return match ($documentType) {
            'goods_receipt' => [$this->goodsReceipt($document)],
            'purchase_invoice' => [$this->purchaseInvoice($document)],
            'purchase_return' => [$this->purchaseReturn($document)],
            'sales_invoice' => $this->salesInvoice($document),
            'sales_return' => $this->salesReturn($document),
            'receipt' => [$this->receipt($document)],
            'payment' => [$this->payment($document)],
            'expense' => [$this->expense($document)],
            'stock_adjustment' => [$this->stockAdjustment($document)],
            'stock_opname' => [$this->stockOpname($document)],
            'cash_opening' => [$this->cashOpening($document)],
            'cash_transfer' => [$this->cashTransfer($document)],
            default => [],
        };
    }

    public function goodsReceipt(GoodsReceipt $receipt): JournalDraft
    {
        $receipt->loadMissing('company');
        $amount = round((float) $receipt->total_value, 4);

        return $this->draft(
            $receipt->company,
            'goods_receipt',
            (int) $receipt->getKey(),
            'goods_receipt',
            $receipt->receipt_date,
            $this->lines([
                $this->debit($this->accounts->id($receipt->company, 'inventory'), $amount),
                $this->credit($this->accounts->id($receipt->company, 'grni'), $amount),
            ], $receipt->branch_id, 'supplier', $receipt->supplier_id),
            'Penerimaan barang '.$receipt->number,
            $receipt->number,
            $receipt->branch_id,
            $receipt->posted_by ?? $receipt->created_by,
        );
    }

    public function purchaseInvoice(PurchaseInvoice $invoice): JournalDraft
    {
        $invoice->loadMissing('company');
        $net = round((float) $invoice->subtotal, 4);
        $tax = round((float) $invoice->tax_amount, 4);
        $other = round((float) $invoice->other_cost, 4);
        $total = round((float) $invoice->total, 4);

        return $this->draft(
            $invoice->company,
            'purchase_invoice',
            (int) $invoice->getKey(),
            'purchase_invoice',
            $invoice->invoice_date,
            $this->lines([
                $this->debit($this->accounts->id($invoice->company, 'grni'), $net),
                $this->debit($this->accounts->id($invoice->company, 'vat_input'), $tax),
                $this->debit($this->accounts->id($invoice->company, 'inventory'), $other),
                $this->credit($this->accounts->id($invoice->company, 'accounts_payable'), $total),
            ], $invoice->branch_id, 'supplier', $invoice->supplier_id),
            'Faktur pembelian '.$invoice->number,
            $invoice->number,
            $invoice->branch_id,
            $invoice->posted_by ?? $invoice->created_by,
        );
    }

    public function purchaseReturn(PurchaseReturn $return): JournalDraft
    {
        $return->loadMissing('company');
        $net = round((float) $return->subtotal, 4);
        $tax = round((float) $return->tax_amount, 4);
        $total = round((float) $return->total, 4);
        $clearing = $return->settlement === 'credit_note'
            ? $this->accounts->id($return->company, 'accounts_payable')
            : $this->accounts->id($return->company, 'other_receivable');

        return $this->draft(
            $return->company,
            'purchase_return',
            (int) $return->getKey(),
            'purchase_return',
            $return->return_date,
            $this->lines([
                $this->debit($clearing, $total),
                $this->credit($this->accounts->id($return->company, 'inventory'), $net),
                $this->credit($this->accounts->id($return->company, 'vat_input'), $tax),
            ], $return->branch_id, 'supplier', $return->supplier_id),
            'Retur pembelian '.$return->number,
            $return->number,
            $return->branch_id,
            $return->posted_by ?? $return->created_by,
        );
    }

    /**
     * @return list<JournalDraft>
     */
    public function salesInvoice(SalesInvoice $invoice): array
    {
        $invoice->loadMissing('company');
        $paid = round((float) $invoice->paid_amount, 4);
        $outstanding = round((float) $invoice->outstanding_amount, 4);
        $subtotal = round((float) $invoice->subtotal, 4);
        $tax = round((float) $invoice->tax_amount, 4);
        $shipping = round((float) $invoice->shipping_cost, 4);
        $cogs = round((float) $invoice->cost_of_goods, 4);

        $revenue = $this->draft(
            $invoice->company,
            'sales_invoice',
            (int) $invoice->getKey(),
            'sales_invoice',
            $invoice->invoice_date,
            $this->lines([
                $this->debit($this->accounts->id($invoice->company, 'accounts_receivable'), $outstanding),
                $this->debit($this->accounts->id($invoice->company, 'cash'), $paid),
                $this->credit($this->accounts->id($invoice->company, 'sales_revenue'), $subtotal),
                $this->credit($this->accounts->id($invoice->company, 'vat_output'), $tax),
                $this->credit($this->accounts->id($invoice->company, 'other_income'), $shipping),
            ], $invoice->branch_id, 'customer', $invoice->customer_id),
            'Faktur penjualan '.$invoice->number,
            $invoice->number,
            $invoice->branch_id,
            $invoice->posted_by ?? $invoice->created_by,
        );

        $cogsDraft = $this->draft(
            $invoice->company,
            'sales_invoice',
            (int) $invoice->getKey(),
            'sales_cogs',
            $invoice->invoice_date,
            $this->lines([
                $this->debit($this->accounts->id($invoice->company, 'cogs'), $cogs),
                $this->credit($this->accounts->id($invoice->company, 'inventory'), $cogs),
            ], $invoice->branch_id),
            'HPP penjualan '.$invoice->number,
            $invoice->number,
            $invoice->branch_id,
            $invoice->posted_by ?? $invoice->created_by,
        );

        return [$revenue, $cogsDraft];
    }

    /**
     * @return list<JournalDraft>
     */
    public function salesReturn(SalesReturn $return): array
    {
        $return->loadMissing('company');
        $total = round((float) $return->total, 4);
        $cogs = round((float) $return->cost_of_goods, 4);
        $inventoryAccount = $return->restock
            ? $this->accounts->id($return->company, 'inventory')
            : $this->accounts->id($return->company, 'inventory_write_off');

        return [
            $this->draft(
                $return->company,
                'sales_return',
                (int) $return->getKey(),
                'sales_return',
                $return->return_date,
                $this->lines([
                    $this->debit($this->accounts->id($return->company, 'sales_return'), $total),
                    $this->credit($this->accounts->id($return->company, 'accounts_receivable'), $total),
                ], $return->branch_id, 'customer', $return->customer_id),
                'Retur penjualan '.$return->number,
                $return->number,
                $return->branch_id,
                $return->posted_by ?? $return->created_by,
            ),
            $this->draft(
                $return->company,
                'sales_return',
                (int) $return->getKey(),
                'sales_return_cogs',
                $return->return_date,
                $this->lines([
                    $this->debit($inventoryAccount, $cogs),
                    $this->credit($this->accounts->id($return->company, 'cogs'), $cogs),
                ], $return->branch_id),
                'HPP retur penjualan '.$return->number,
                $return->number,
                $return->branch_id,
                $return->posted_by ?? $return->created_by,
            ),
        ];
    }

    public function receipt(Receipt $receipt): JournalDraft
    {
        $receipt->loadMissing(['company', 'cashAccount']);
        $cash = $this->accounts->cashLedger($receipt->company, $receipt->cashAccount);
        $amount = round((float) $receipt->amount, 4);
        $discount = round((float) $receipt->discount_amount, 4);
        $allocated = round((float) $receipt->allocated_amount, 4);
        $unallocated = round(max(0, $amount - $allocated), 4);
        $ar = round($allocated + $discount, 4);

        return $this->draft(
            $receipt->company,
            'receipt',
            (int) $receipt->getKey(),
            'receipt',
            $receipt->receipt_date,
            $this->lines([
                $this->debit($cash->id, $amount),
                $this->debit($this->accounts->id($receipt->company, 'sales_discount'), $discount),
                $this->credit($this->accounts->id($receipt->company, 'accounts_receivable'), $ar),
                $this->credit($this->accounts->id($receipt->company, 'customer_deposit'), $unallocated),
            ], $receipt->branch_id, 'customer', $receipt->customer_id),
            'Penerimaan '.$receipt->number,
            $receipt->number,
            $receipt->branch_id,
            $receipt->posted_by ?? $receipt->created_by,
        );
    }

    public function payment(Payment $payment): JournalDraft
    {
        $payment->loadMissing(['company', 'cashAccount']);
        $cash = $this->accounts->cashLedger($payment->company, $payment->cashAccount);
        $amount = round((float) $payment->amount, 4);
        $discount = round((float) $payment->discount_amount, 4);
        $allocated = round((float) $payment->allocated_amount, 4);
        $unallocated = round(max(0, $amount - $allocated), 4);
        $ap = round($allocated + $discount, 4);

        return $this->draft(
            $payment->company,
            'payment',
            (int) $payment->getKey(),
            'payment',
            $payment->payment_date,
            $this->lines([
                $this->debit($this->accounts->id($payment->company, 'accounts_payable'), $ap),
                $this->debit($this->accounts->id($payment->company, 'prepaid_expense'), $unallocated),
                $this->credit($cash->id, $amount),
                $this->credit($this->accounts->id($payment->company, 'purchase_discount'), $discount),
            ], $payment->branch_id, 'supplier', $payment->supplier_id),
            'Pembayaran '.$payment->number,
            $payment->number,
            $payment->branch_id,
            $payment->posted_by ?? $payment->created_by,
        );
    }

    public function expense(Expense $expense): JournalDraft
    {
        $expense->loadMissing(['company', 'cashAccount', 'category']);
        $cash = $this->accounts->cashLedger($expense->company, $expense->cashAccount);
        $expenseAccountId = $expense->category?->account_id
            ?: $this->accounts->id($expense->company, 'expense_other');
        $amount = round((float) $expense->amount, 4);
        $tax = round((float) $expense->tax_amount, 4);
        $total = round((float) $expense->total, 4);

        return $this->draft(
            $expense->company,
            'expense',
            (int) $expense->getKey(),
            'expense',
            $expense->expense_date,
            $this->lines([
                $this->debit((int) $expenseAccountId, $amount, $expense->cost_center_id),
                $this->debit($this->accounts->id($expense->company, 'vat_input'), $tax, $expense->cost_center_id),
                $this->credit($cash->id, $total),
            ], $expense->branch_id, 'supplier', $expense->supplier_id),
            'Biaya '.$expense->number,
            $expense->number,
            $expense->branch_id,
            $expense->posted_by ?? $expense->created_by,
        );
    }

    public function stockAdjustment(StockAdjustment $adjustment): JournalDraft
    {
        $adjustment->loadMissing('company');
        $net = round((float) $adjustment->total_value, 4);
        $isGain = $net >= 0;
        $amount = abs($net);
        $variance = in_array($adjustment->reason, ['damaged', 'expired'], true)
            ? $this->accounts->id($adjustment->company, 'inventory_write_off')
            : $this->accounts->id($adjustment->company, 'inventory_variance');
        $inventory = $this->accounts->id($adjustment->company, 'inventory');

        return $this->draft(
            $adjustment->company,
            'stock_adjustment',
            (int) $adjustment->getKey(),
            'stock_adjustment',
            $adjustment->adjustment_date,
            $this->lines(
                $isGain
                    ? [
                        $this->debit($inventory, $amount),
                        $this->credit($variance, $amount),
                    ]
                    : [
                        $this->debit($variance, $amount),
                        $this->credit($inventory, $amount),
                    ],
                $adjustment->branch_id,
            ),
            'Penyesuaian stok '.$adjustment->number,
            $adjustment->number,
            $adjustment->branch_id,
            $adjustment->posted_by ?? $adjustment->created_by,
        );
    }

    public function stockOpname(StockOpname $opname): JournalDraft
    {
        $opname->loadMissing('company');
        $net = round((float) $opname->difference_value, 4);
        $isGain = $net >= 0;
        $amount = abs($net);
        $inventory = $this->accounts->id($opname->company, 'inventory');
        $variance = $this->accounts->id($opname->company, 'inventory_variance');

        return $this->draft(
            $opname->company,
            'stock_opname',
            (int) $opname->getKey(),
            'stock_opname',
            $opname->opname_date,
            $this->lines(
                $isGain
                    ? [
                        $this->debit($inventory, $amount),
                        $this->credit($variance, $amount),
                    ]
                    : [
                        $this->debit($variance, $amount),
                        $this->credit($inventory, $amount),
                    ],
                $opname->branch_id,
            ),
            'Opname stok '.$opname->number,
            $opname->number,
            $opname->branch_id,
            $opname->posted_by ?? $opname->created_by,
        );
    }

    public function cashOpening(CashTransaction $txn): JournalDraft
    {
        $txn->loadMissing(['company', 'cashAccount']);
        $cash = $this->accounts->cashLedger($txn->company, $txn->cashAccount);
        $amount = round((float) $txn->amount, 4);

        return $this->draft(
            $txn->company,
            'cash_opening',
            (int) $txn->getKey(),
            'cash_opening',
            $txn->transaction_date,
            $this->lines([
                $this->debit($cash->id, $amount),
                $this->credit($this->accounts->id($txn->company, 'capital'), $amount),
            ], $txn->branch_id),
            $txn->description ?: 'Saldo awal kas',
            $txn->document_number,
            $txn->branch_id,
            $txn->created_by,
        );
    }

    public function cashTransfer(CashTransferPayload $transfer): JournalDraft
    {
        $from = $transfer->from->loadMissing('company');
        $company = $from->company;
        $source = $this->accounts->cashLedger($company, $transfer->from);
        $dest = $this->accounts->cashLedger($company, $transfer->to);
        $amount = round($transfer->amount, 4);

        return $this->draft(
            $company,
            'cash_transfer',
            $transfer->getKey(),
            'cash_transfer',
            $transfer->out->transaction_date,
            $this->lines([
                $this->debit($dest->id, $amount),
                $this->credit($source->id, $amount),
            ], $transfer->out->branch_id),
            $transfer->out->description ?: 'Transfer kas',
            $transfer->out->document_number,
            $transfer->out->branch_id,
            $transfer->out->created_by,
        );
    }

    /**
     * @param  list<array<string, mixed>|null>  $rows
     * @return list<array<string, mixed>>
     */
    private function lines(array $rows, ?int $branchId = null, ?string $partnerType = null, ?int $partnerId = null): array
    {
        $out = [];

        foreach ($rows as $row) {
            if ($row === null) {
                continue;
            }

            $row['branch_id'] = $row['branch_id'] ?? $branchId;
            $row['partner_type'] = $row['partner_type'] ?? $partnerType;
            $row['partner_id'] = $row['partner_id'] ?? $partnerId;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function debit(int $accountId, float $amount, ?int $costCenterId = null): ?array
    {
        $amount = round($amount, 4);

        if ($amount < 0.0001) {
            return null;
        }

        return ['account_id' => $accountId, 'debit' => $amount, 'credit' => 0, 'cost_center_id' => $costCenterId];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function credit(int $accountId, float $amount, ?int $costCenterId = null): ?array
    {
        $amount = round($amount, 4);

        if ($amount < 0.0001) {
            return null;
        }

        return ['account_id' => $accountId, 'debit' => 0, 'credit' => $amount, 'cost_center_id' => $costCenterId];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function draft(
        Company $company,
        string $documentType,
        int $documentId,
        string $purpose,
        mixed $entryDate,
        array $lines,
        string $description,
        ?string $documentNumber,
        ?int $branchId,
        ?int $createdBy,
    ): JournalDraft {
        return new JournalDraft(
            company: $company,
            documentType: $documentType,
            documentId: $documentId,
            purpose: $purpose,
            entryDate: Carbon::parse($entryDate),
            lines: $lines,
            description: $description,
            documentNumber: $documentNumber,
            branchId: $branchId,
            createdBy: $createdBy,
        );
    }
}
