<?php

declare(strict_types=1);

use App\Modules\Supplier\Models\Supplier;

function makeSupplier(int $companyId, array $attributes = []): Supplier
{
    return Supplier::create(array_merge([
        'company_id' => $companyId,
        'code' => 'SUP-'.str()->random(4),
        'name' => 'PT Pemasok Uji',
        'lead_time_days' => 3,
    ], $attributes));
}
