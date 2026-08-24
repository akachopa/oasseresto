<?php

declare(strict_types=1);

use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Core\Enums\ApprovalStatus;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Purchase\Services\PurchaseOrderService;
use App\Modules\Reporting\Livewire\MyTasks;
use Livewire\Livewire;

it('menampilkan antrian approval di Tugas Saya dan menyetujui dari sana', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    $manager = $this->makeUser($company, 'Branch Manager', $branch);

    ApprovalRule::create([
        'company_id' => $company->id,
        'document_type' => 'purchase_order',
        'name' => 'PO wajib manajer',
        'sequence' => 1,
        'min_amount' => 1_000_000,
        'approver_role' => 'Branch Manager',
    ]);

    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);
    $supplier = makeSupplier($company->id);

    $this->actingAs($manager);

    $order = app(PurchaseOrderService::class)->save(null, [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 200, 'unit_price' => 10_000],
    ]);

    app(PurchaseOrderService::class)->submit($order);

    $request = ApprovalRequest::query()->where('document_id', $order->id)->firstOrFail();
    expect($request->status)->toBe(ApprovalStatus::Pending);

    $this->actingAs($manager)->get(route('tasks.index'))
        ->assertOk()
        ->assertSee($order->number);

    Livewire::actingAs($manager)
        ->test(MyTasks::class)
        ->assertSee($order->number)
        ->call('approve', $request->id);

    expect($request->fresh()->status)->toBe(ApprovalStatus::Approved);
    expect($order->fresh()->status)->toBe(DocumentStatus::Approved);
});
