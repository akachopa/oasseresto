<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

enum AlertSeverity: string
{
    case Critical = 'critical';
    case Warning = 'warning';
    case Info = 'info';

    public function weight(): int
    {
        return match ($this) {
            self::Critical => 30,
            self::Warning => 20,
            self::Info => 10,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Kritis',
            self::Warning => 'Peringatan',
            self::Info => 'Informasi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Critical => 'danger',
            self::Warning => 'warning',
            self::Info => 'info',
        };
    }
}
