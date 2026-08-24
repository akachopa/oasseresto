<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

enum ReceivableStatus: string
{
    case Open = 'open';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case WrittenOff = 'written_off';
    case Cancelled = 'cancelled';

    public function isOutstanding(): bool
    {
        return in_array($this, [self::Open, self::PartiallyPaid, self::Overdue], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Belum Dibayar',
            self::PartiallyPaid => 'Dibayar Sebagian',
            self::Paid => 'Lunas',
            self::Overdue => 'Jatuh Tempo',
            self::WrittenOff => 'Dihapus Buku',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::PartiallyPaid => 'warning',
            self::Paid => 'success',
            self::Overdue => 'danger',
            self::WrittenOff, self::Cancelled => 'muted',
        };
    }
}
