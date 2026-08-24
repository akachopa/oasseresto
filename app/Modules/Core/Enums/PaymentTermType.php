<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

enum PaymentTermType: string
{
    case Cash = 'cash';
    case Net7 = 'net_7';
    case Net14 = 'net_14';
    case Net30 = 'net_30';
    case Net45 = 'net_45';
    case Net60 = 'net_60';
    case Custom = 'custom';

    public function days(): int
    {
        return match ($this) {
            self::Cash => 0,
            self::Net7 => 7,
            self::Net14 => 14,
            self::Net30 => 30,
            self::Net45 => 45,
            self::Net60 => 60,
            self::Custom => 0,
        };
    }

    public function isCredit(): bool
    {
        return $this !== self::Cash;
    }

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Net7 => 'Tempo 7 Hari',
            self::Net14 => 'Tempo 14 Hari',
            self::Net30 => 'Tempo 30 Hari',
            self::Net45 => 'Tempo 45 Hari',
            self::Net60 => 'Tempo 60 Hari',
            self::Custom => 'Tempo Kustom',
        };
    }

    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case) => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
