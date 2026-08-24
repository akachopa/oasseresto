<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

enum JournalStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Posted => 'Diposting',
            self::Reversed => 'Dibalik',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'muted',
            self::Posted => 'success',
            self::Reversed => 'warning',
        };
    }
}
