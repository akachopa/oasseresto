<?php

declare(strict_types=1);

namespace App\Modules\Accounting\DTO;

use App\Modules\Finance\Models\CashAccount;
use App\Modules\Finance\Models\CashTransaction;

class CashTransferPayload
{
    public function __construct(
        public CashAccount $from,
        public CashAccount $to,
        public CashTransaction $out,
        public CashTransaction $in,
        public float $amount,
    ) {}

    public function getKey(): int
    {
        return (int) $this->out->getKey();
    }
}
