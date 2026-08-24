<?php

declare(strict_types=1);

namespace App\Modules\Core\Support;

class Money
{
    public static function rupiah(float|int|string|null $value, int $decimals = 0): string
    {
        return 'Rp '.number_format((float) $value, $decimals, ',', '.');
    }

    /**
     * Format ringkas untuk KPI dashboard: 1,2 jt / 3,4 m.
     */
    public static function compact(float|int|string|null $value): string
    {
        $number = (float) $value;
        $sign = $number < 0 ? '-' : '';
        $abs = abs($number);

        return match (true) {
            $abs >= 1_000_000_000_000 => $sign.'Rp '.self::trim($abs / 1_000_000_000_000).' t',
            $abs >= 1_000_000_000 => $sign.'Rp '.self::trim($abs / 1_000_000_000).' m',
            $abs >= 1_000_000 => $sign.'Rp '.self::trim($abs / 1_000_000).' jt',
            $abs >= 1_000 => $sign.'Rp '.self::trim($abs / 1_000).' rb',
            default => $sign.'Rp '.number_format($abs, 0, ',', '.'),
        };
    }

    public static function quantity(float|int|string|null $value): string
    {
        $number = (float) $value;

        return rtrim(rtrim(number_format($number, 4, ',', '.'), '0'), ',');
    }

    public static function percent(float|int|string|null $value, int $decimals = 1): string
    {
        return number_format((float) $value, $decimals, ',', '.').'%';
    }

    private static function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',');
    }
}
