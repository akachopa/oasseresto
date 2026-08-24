<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

enum DeliveryStatus: string
{
    case Ready = 'ready';
    case Picking = 'picking';
    case Packed = 'packed';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case Partial = 'partial';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'Siap',
            self::Picking => 'Picking',
            self::Packed => 'Dikemas',
            self::Dispatched => 'Dikirim',
            self::Delivered => 'Diterima',
            self::Partial => 'Sebagian',
            self::Failed => 'Gagal Kirim',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Ready, self::Picking, self::Packed => 'info',
            self::Dispatched => 'warning',
            self::Delivered => 'success',
            self::Partial => 'warning',
            self::Failed => 'danger',
        };
    }
}
