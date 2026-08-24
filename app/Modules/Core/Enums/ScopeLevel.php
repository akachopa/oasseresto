<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

enum ScopeLevel: string
{
    case Company = 'company';
    case Branch = 'branch';
    case Warehouse = 'warehouse';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Seluruh Company',
            self::Branch => 'Cabang Tertentu',
            self::Warehouse => 'Gudang Tertentu',
        };
    }
}
