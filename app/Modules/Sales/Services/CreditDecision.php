<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

/**
 * Hasil pemeriksaan kredit beserta alasannya, supaya UI dan dokumen bisa
 * menjelaskan kenapa order diblokir atau butuh approval (PLAN 17).
 */
readonly class CreditDecision
{
    public function __construct(
        public string $status,
        public float $creditLimit,
        public float $outstanding,
        public float $exposure,
        public float $available,
        public float $overdueAmount,
        public int $overdueDays,
        public ?string $message = null,
    ) {}

    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    public function requiresApproval(): bool
    {
        return $this->status === 'approval';
    }

    public function isClear(): bool
    {
        return $this->status === 'ok';
    }

    public function label(): string
    {
        return match ($this->status) {
            'blocked' => 'Diblokir',
            'approval' => 'Butuh Approval',
            'warning' => 'Peringatan',
            default => 'Aman',
        };
    }

    public function color(): string
    {
        return match ($this->status) {
            'blocked' => 'danger',
            'approval', 'warning' => 'warning',
            default => 'success',
        };
    }
}
