<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

enum AlertType: string
{
    case StockBelowReorder = 'stock_below_reorder';
    case StockExpired = 'stock_expired';
    case StockNearExpiry = 'stock_near_expiry';
    case ArOverdue = 'ar_overdue';
    case ApOverdue = 'ap_overdue';
    case CreditLimit = 'credit_limit';
    case NegativeCash = 'negative_cash';
    case CashLow = 'cash_low';
    case PendingApproval = 'pending_approval';
    case PickingBacklog = 'picking_backlog';
    case OpenPoOverdue = 'open_po_overdue';

    public function label(): string
    {
        return match ($this) {
            self::StockBelowReorder => 'Stok di bawah reorder',
            self::StockExpired => 'Batch kedaluwarsa',
            self::StockNearExpiry => 'Batch mendekati kedaluwarsa',
            self::ArOverdue => 'Piutang jatuh tempo',
            self::ApOverdue => 'Hutang jatuh tempo',
            self::CreditLimit => 'Credit limit terlampaui',
            self::NegativeCash => 'Saldo kas negatif',
            self::CashLow => 'Saldo kas menipis',
            self::PendingApproval => 'Approval tertunda',
            self::PickingBacklog => 'Antrian picking',
            self::OpenPoOverdue => 'PO terlambat diterima',
        };
    }

    public function defaultSeverity(): AlertSeverity
    {
        return match ($this) {
            self::NegativeCash, self::StockExpired, self::CreditLimit => AlertSeverity::Critical,
            self::StockBelowReorder, self::ArOverdue, self::ApOverdue, self::CashLow,
            self::OpenPoOverdue, self::PendingApproval => AlertSeverity::Warning,
            self::StockNearExpiry, self::PickingBacklog => AlertSeverity::Info,
        };
    }
}
