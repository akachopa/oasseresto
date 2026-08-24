<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

enum CostingMethod: string
{
    case Fifo = 'fifo';
    case WeightedAverage = 'weighted_average';

    public function label(): string
    {
        return match ($this) {
            self::Fifo => 'FIFO',
            self::WeightedAverage => 'Rata-rata Tertimbang',
        };
    }
}
