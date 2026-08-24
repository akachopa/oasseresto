<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

enum AccountingPeriodStatus: string
{
    case Open = 'open';
    case Closing = 'closing';
    case Closed = 'closed';
    case Reopened = 'reopened';

    public function allowsPosting(): bool
    {
        return in_array($this, [self::Open, self::Closing, self::Reopened], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Terbuka',
            self::Closing => 'Proses Tutup Buku',
            self::Closed => 'Ditutup',
            self::Reopened => 'Dibuka Kembali',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open, self::Reopened => 'success',
            self::Closing => 'warning',
            self::Closed => 'muted',
        };
    }
}
