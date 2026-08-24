<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Exceptions;

use App\Modules\Core\Support\Money;
use App\Modules\Product\Models\Product;
use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public static function for(Product $product, float $requested, float $available, string $warehouseName): self
    {
        return new self(sprintf(
            'Stok %s di %s tinggal %s, diminta %s.',
            $product->sku,
            $warehouseName,
            Money::quantity($available),
            Money::quantity($requested),
        ));
    }
}
