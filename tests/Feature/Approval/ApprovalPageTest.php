<?php

declare(strict_types=1);

use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Core\Enums\ApprovalStatus;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Purchase\Services\PurchaseOrderService;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    ['company' => $this->company, 'branch' => $this->branch, 'warehouse' => $this->warehouse] = $this->bootCompany();
});

it('membuka halaman aturan dan permintaan approval untuk owner', function (): void {
    $owner = $this->makeUser($this->company, 'Owner');

    $this->actingAs($owner)->get(route('approval.rules.index'))->assertOk();
    $this->actingAs($owner)->get(route('approval.rules.create'))->assertOk();
    $this->actingAs($owner)->get(route('approval.requests.index'))->assertOk();
});

it('menolak akses aturan approval untuk salesman dan kasir', function (): void {
    $salesman = $this->makeUser($this->company, 'Salesman', $this->branch);
    $cashier = $this->makeUser($this->company, 'Cashier', $this->branch);

    $this->actingAs($salesman)->get(route('approval.rules.index'))->assertForbidden();
    $this->actingAs($cashier)->get(route('approval.rules.index'))->assertForbidden();
    $this->actingAs($salesman)->get(route('approval.requests.index'))->assertForbidden();
});

it('mengembalikan data aturan approval dalam format DataTables', function (): void {
    $owner = $this->makeUser($this->company, 'Owner');

    ApprovalRule::create([
        'document_type' => 'expense',
        'name' => 'Biaya uji',
        'sequence' => 1,
        'min_amount' => 1_000_000,
        'approver_role' => 'Owner',
    ]);

    $response = $this->actingAs($owner)->postJson(route('approval.rules.data'), [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

    expect($response->json('recordsTotal'))->toBeGreaterThanOrEqual(1);
    expect($response->json('data.0'))->toHaveKeys(['document', 'name', 'sequence', 'aksi']);
});

it('membuat, mengubah, dan menghapus aturan approval', function (): void {
    $owner = $this->makeUser($this->company, 'Owner');

    $this->actingAs($owner)->post(route('approval.rules.store'), [
        'document_type' => 'expense',
        'name' => 'Biaya di atas 1 juta',
        'sequence' => 1,
        'min_amount' => 1_000_000,
        'trigger' => 'always',
        'approver_role' => 'Owner',
        'is_active' => '1',
    ])->assertRedirect(route('approval.rules.index'));

    $rule = ApprovalRule::where('name', 'Biaya di atas 1 juta')->firstOrFail();
    expect($rule->company_id)->toBe($this->company->id);
    expect($rule->approver_role)->toBe('Owner');

    expect(Activity::query()->where('description', 'Aturan approval ditambahkan')->exists())->toBeTrue();

    $this->actingAs($owner)->put(route('approval.rules.update', $rule), [
        'document_type' => 'expense',
        'name' => 'Biaya di atas 2 juta',
        'sequence' => 1,
        'min_amount' => 2_000_000,
        'trigger' => 'always',
        'approver_role' => 'Owner',
        'is_active' => '1',
    ])->assertRedirect(route('approval.rules.index'));

    expect($rule->fresh()->name)->toBe('Biaya di atas 2 juta');
    expect($rule->fresh()->min_amount)->toBe(2_000_000.0);

    $this->actingAs($owner)->delete(route('approval.rules.hapus', $rule))->assertRedirect();
    expect(ApprovalRule::whereKey($rule->id)->exists())->toBeFalse();
});

it('menolak aturan tanpa penyetuju', function (): void {
    $owner = $this->makeUser($this->company, 'Owner');

    $this->actingAs($owner)->post(route('approval.rules.store'), [
        'document_type' => 'expense',
        'name' => 'Tanpa penyetuju',
        'sequence' => 1,
        'min_amount' => 0,
        'trigger' => 'always',
        'is_active' => '1',
    ])->assertSessionHasErrors('approver_role');
});

it('menampilkan detail permintaan dan menyetujui lewat halaman approval', function (): void {
    $manager = $this->makeUser($this->company, 'Branch Manager', $this->branch);

    ApprovalRule::create([
        'document_type' => 'purchase_order',
        'name' => 'PO wajib manajer',
        'sequence' => 1,
        'min_amount' => 1_000_000,
        'approver_role' => 'Branch Manager',
    ]);

    ['product' => $product, 'pcs' => $pcs] = stockProduct($this->company->id);
    $supplier = makeSupplier($this->company->id);

    $this->actingAs($manager);

    $order = app(PurchaseOrderService::class)->save(null, [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 200, 'unit_price' => 10_000],
    ]);

    app(PurchaseOrderService::class)->submit($order);

    $request = ApprovalRequest::query()->where('document_id', $order->id)->firstOrFail();
    expect($request->status)->toBe(ApprovalStatus::Pending);

    $this->actingAs($manager)
        ->get(route('approval.requests.detail', $request))
        ->assertOk()
        ->assertSee($order->number);

    $this->actingAs($manager)
        ->post(route('approval.requests.approve', $request), ['note' => 'OK'])
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(ApprovalStatus::Approved);
    expect($order->fresh()->status)->toBe(DocumentStatus::Approved);
});

it('membatasi dashboard Horizon pada pemegang setting.user.manage', function (): void {
    $owner = $this->makeUser($this->company, 'Owner');
    $salesman = $this->makeUser($this->company, 'Salesman', $this->branch);

    expect(Gate::forUser($owner)->allows('viewHorizon'))->toBeTrue();
    expect(Gate::forUser($salesman)->allows('viewHorizon'))->toBeFalse();

    $this->actingAs($salesman)->get('/horizon')->assertForbidden();
});
