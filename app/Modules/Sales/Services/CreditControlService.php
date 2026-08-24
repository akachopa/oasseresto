<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Support\Money;
use App\Modules\Customer\Models\Customer;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesOrder;

/**
 * Kontrol kredit customer (PLAN 17). Eksposur dihitung dari piutang berjalan
 * ditambah order yang sudah disetujui tapi belum ditagih, sehingga limit tidak
 * bisa ditembus dengan memecah order menjadi beberapa dokumen.
 */
class CreditControlService
{
    public function evaluate(Customer $customer, float $orderAmount, ?PaymentTermType $term = null, ?int $excludeOrderId = null): CreditDecision
    {
        $term ??= $customer->effectivePaymentTerm();

        $receivable = $this->receivableExposure($customer);
        $pipeline = $this->pipelineExposure($customer, $excludeOrderId);
        $outstanding = round($receivable['amount'] + $pipeline, 4);
        $exposure = round($outstanding + $orderAmount, 4);
        $limit = (float) $customer->credit_limit;
        $available = round($limit - $outstanding, 4);

        // Penjualan tunai tidak memakai limit, jadi tidak pernah diblokir.
        if (! $term->isCredit()) {
            return $this->decision('ok', $customer, $limit, $outstanding, $exposure, $available, $receivable);
        }

        if (! $customer->is_active) {
            return $this->decision(
                'blocked',
                $customer, $limit, $outstanding, $exposure, $available, $receivable,
                'Customer tidak aktif; penjualan kredit tidak diizinkan.',
            );
        }

        $mode = (string) config('oasse.credit.mode', 'approval');

        if ($receivable['overdue_amount'] > 0) {
            $message = sprintf(
                'Ada tagihan jatuh tempo %s (%d hari).',
                Money::rupiah($receivable['overdue_amount']),
                $receivable['overdue_days'],
            );

            return $this->decision(
                $mode === 'warn' ? 'warning' : ($mode === 'block' ? 'blocked' : 'approval'),
                $customer, $limit, $outstanding, $exposure, $available, $receivable, $message,
            );
        }

        if ($limit <= 0) {
            return $this->decision(
                $mode === 'block' ? 'blocked' : 'approval',
                $customer, $limit, $outstanding, $exposure, $available, $receivable,
                'Customer belum punya credit limit untuk penjualan tempo.',
            );
        }

        if ($exposure > $limit) {
            $message = sprintf(
                'Order membuat eksposur %s melebihi limit %s.',
                Money::rupiah($exposure),
                Money::rupiah($limit),
            );

            return $this->decision(
                match ($mode) {
                    'block' => 'blocked',
                    'warn' => 'warning',
                    default => 'approval',
                },
                $customer, $limit, $outstanding, $exposure, $available, $receivable, $message,
            );
        }

        // Mendekati limit tetap dilewatkan, tapi tetap diberi tahu.
        if ($limit > 0 && $exposure >= $limit * 0.9) {
            return $this->decision(
                'warning',
                $customer, $limit, $outstanding, $exposure, $available, $receivable,
                'Eksposur sudah di atas 90% credit limit.',
            );
        }

        return $this->decision('ok', $customer, $limit, $outstanding, $exposure, $available, $receivable);
    }

    /**
     * Sinkronkan ringkasan piutang di master customer supaya dashboard dan
     * pemeriksaan kredit memakai angka yang sama.
     */
    public function refreshCustomer(Customer $customer): Customer
    {
        $exposure = $this->receivableExposure($customer);

        $customer->outstanding_amount = $exposure['amount'];
        $customer->overdue_amount = $exposure['overdue_amount'];
        $customer->saveQuietly();

        return $customer;
    }

    /**
     * @return array{amount: float, overdue_amount: float, overdue_days: int}
     */
    private function receivableExposure(Customer $customer): array
    {
        $rows = Receivable::query()
            ->where('customer_id', $customer->getKey())
            ->outstanding()
            ->get(['outstanding_amount', 'due_date']);

        $overdue = $rows->filter(fn (Receivable $row) => $row->isOverdue());

        return [
            'amount' => round((float) $rows->sum('outstanding_amount'), 4),
            'overdue_amount' => round((float) $overdue->sum('outstanding_amount'), 4),
            'overdue_days' => (int) ($overdue->max(fn (Receivable $row) => $row->daysOverdue()) ?? 0),
        ];
    }

    /**
     * Order yang sudah disetujui tapi belum ditagih tetap memakai limit,
     * karena barangnya akan keluar dan tagihannya pasti muncul.
     */
    private function pipelineExposure(Customer $customer, ?int $excludeOrderId): float
    {
        $orders = SalesOrder::query()
            ->where('customer_id', $customer->getKey())
            ->whereIn('status', [DocumentStatus::Approved, DocumentStatus::PartiallyProcessed])
            ->when($excludeOrderId, fn ($query) => $query->whereKeyNot($excludeOrderId))
            ->get(['id', 'total']);

        if ($orders->isEmpty()) {
            return 0.0;
        }

        /*
         * Bagian order yang sudah ditagih tidak dihitung dua kali karena
         * nilainya sudah muncul sebagai piutang.
         */
        $invoiced = SalesInvoice::query()
            ->whereIn('sales_order_id', $orders->pluck('id'))
            ->where('status', DocumentStatus::Posted)
            ->groupBy('sales_order_id')
            ->selectRaw('sales_order_id, SUM(total) as invoiced_total')
            ->pluck('invoiced_total', 'sales_order_id');

        return round($orders->sum(
            fn (SalesOrder $order) => max(0, (float) $order->total - (float) ($invoiced[$order->getKey()] ?? 0)),
        ), 4);
    }

    /**
     * @param  array{amount: float, overdue_amount: float, overdue_days: int}  $receivable
     */
    private function decision(
        string $status,
        Customer $customer,
        float $limit,
        float $outstanding,
        float $exposure,
        float $available,
        array $receivable,
        ?string $message = null,
    ): CreditDecision {
        return new CreditDecision(
            status: $status,
            creditLimit: $limit,
            outstanding: $outstanding,
            exposure: $exposure,
            available: $available,
            overdueAmount: $receivable['overdue_amount'],
            overdueDays: $receivable['overdue_days'],
            message: $message,
        );
    }
}
