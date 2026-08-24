<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

enum PriceRuleType: string
{
    case PriceLevel = 'price_level';
    case CustomerGroup = 'customer_group';
    case Customer = 'customer';
    case QuantityTier = 'quantity_tier';
    case PaymentTerm = 'payment_term';
    case Branch = 'branch';
    case Promotion = 'promotion';

    /**
     * Semakin tinggi angkanya semakin spesifik dan semakin diprioritaskan.
     */
    public function priority(): int
    {
        return match ($this) {
            self::PriceLevel => 10,
            self::Branch => 20,
            self::PaymentTerm => 30,
            self::CustomerGroup => 40,
            self::QuantityTier => 50,
            self::Customer => 60,
            self::Promotion => 70,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PriceLevel => 'Level Harga',
            self::CustomerGroup => 'Grup Customer',
            self::Customer => 'Customer Tertentu',
            self::QuantityTier => 'Tier Kuantitas',
            self::PaymentTerm => 'Termin Pembayaran',
            self::Branch => 'Cabang',
            self::Promotion => 'Promosi',
        };
    }
}
