<?php

declare(strict_types=1);

use App\Modules\Core\Enums\AlertType;
use App\Modules\Notification\Models\Alert;
use App\Modules\Notification\Services\AlertEngine;

it('membuat alert stok di bawah reorder dan menyelesaikannya setelah stok cukup', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product] = stockProduct($company->id);

    $product->update(['reorder_point' => 50, 'minimum_stock' => 20]);
    receiveStock($product, $warehouse->id, 10, 8_000);

    $engine = app(AlertEngine::class);
    $open = $engine->scan($company->id);

    $alert = collect($open)->first(fn (Alert $row) => $row->type === AlertType::StockBelowReorder);

    expect($alert)->not->toBeNull();
    expect($alert->is_resolved)->toBeFalse();
    expect($alert->count)->toBeGreaterThan(0);

    receiveStock($product, $warehouse->id, 80, 8_000);

    $engine->scan($company->id);

    expect(Alert::query()->unresolved()->where('type', AlertType::StockBelowReorder)->exists())->toBeFalse();
    expect(Alert::query()->where('type', AlertType::StockBelowReorder)->where('is_resolved', true)->exists())->toBeTrue();
});

it('membuat alert saldo kas negatif per akun', function (): void {
    ['company' => $company] = $this->bootCompany();

    $account = makeCashAccount($company->id, ['balance' => -50_000]);

    $open = app(AlertEngine::class)->scan($company->id);
    $alert = collect($open)->first(fn (Alert $row) => $row->type === AlertType::NegativeCash);

    expect($alert)->not->toBeNull();
    expect($alert->entity_id)->toBe($account->id);
});
