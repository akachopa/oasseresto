<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Posted = 'posted';
    case PartiallyProcessed = 'partially_processed';
    case Completed = 'completed';
    case Reversed = 'reversed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Diajukan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Posted => 'Diposting',
            self::PartiallyProcessed => 'Sebagian',
            self::Completed => 'Selesai',
            self::Reversed => 'Dibalik',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'muted',
            self::Submitted => 'info',
            self::Approved => 'info',
            self::Rejected, self::Cancelled => 'danger',
            self::Posted, self::Completed => 'success',
            self::PartiallyProcessed => 'warning',
            self::Reversed => 'warning',
        };
    }

    /**
     * Dokumen yang sudah final tidak boleh diedit langsung (PLAN 52).
     */
    public function isImmutable(): bool
    {
        return in_array($this, [self::Posted, self::Completed, self::Reversed, self::Cancelled], true);
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }
}
