<?php

declare(strict_types=1);

use App\Modules\Customer\Models\Customer;

function makeCustomer(int $companyId, array $attributes = []): Customer
{
    return Customer::create(array_merge([
        'company_id' => $companyId,
        'code' => 'CUS-'.str()->random(4),
        'name' => 'Toko Uji',
        'payment_term' => 'net_30',
        'credit_limit' => 10_000_000,
        'is_active' => true,
    ], $attributes));
}
