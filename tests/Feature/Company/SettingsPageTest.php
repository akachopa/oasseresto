<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Warehouse;

beforeEach(function (): void {
    ['company' => $this->company, 'branch' => $this->branch] = $this->bootCompany();
});

it('membuka halaman cabang, gudang, user, dan role untuk owner', function (): void {
    $owner = $this->makeUser($this->company, 'Owner');

    foreach ([
        route('settings.company'),
        route('settings.branches.index'),
        route('settings.warehouses.index'),
        route('settings.tax-codes.index'),
        route('team.users.index'),
        route('team.roles.index'),
        route('team.audit.index'),
    ] as $url) {
        $this->actingAs($owner)->get($url)->assertOk();
    }
});

it('menolak akses pengaturan untuk kasir', function (): void {
    $cashier = $this->makeUser($this->company, 'Cashier', $this->branch);

    $this->actingAs($cashier)->get(route('settings.branches.index'))->assertForbidden();
    $this->actingAs($cashier)->get(route('team.users.index'))->assertForbidden();
});

it('mengembalikan data cabang dalam format DataTables', function (): void {
    $owner = $this->makeUser($this->company, 'Owner');

    $response = $this->actingAs($owner)->postJson(route('settings.branches.data'), [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'])
        ->assertJsonPath('recordsTotal', 2);

    expect($response->json('data.0'))->toHaveKeys(['code', 'name', 'city', 'aksi']);
});

it('membuat dan menghapus cabang', function (): void {
    $owner = $this->makeUser($this->company, 'Owner');

    $this->actingAs($owner)->post(route('settings.branches.store'), [
        'code' => 'MNA',
        'name' => 'Manna',
        'type' => 'branch',
        'city' => 'Manna',
        'is_active' => '1',
    ])->assertRedirect(route('settings.branches.index'));

    $branch = Branch::where('code', 'MNA')->firstOrFail();
    expect($branch->company_id)->toBe($this->company->id);

    $this->actingAs($owner)->delete(route('settings.branches.hapus', $branch))->assertRedirect();
    expect(Branch::where('code', 'MNA')->exists())->toBeFalse();
});

it('menolak menghapus gudang default', function (): void {
    $owner = $this->makeUser($this->company, 'Owner');
    $warehouse = Warehouse::where('code', 'GU-BKL')->firstOrFail();

    $this->actingAs($owner)
        ->delete(route('settings.warehouses.hapus', $warehouse))
        ->assertSessionHas('error');

    expect(Warehouse::whereKey($warehouse->id)->exists())->toBeTrue();
});

it('menyimpan user baru dengan role dan scope gudang', function (): void {
    $owner = $this->makeUser($this->company, 'Owner');
    $warehouse = Warehouse::where('code', 'GU-BKL')->firstOrFail();

    $this->actingAs($owner)->post(route('team.users.store'), [
        'name' => 'Staff Gudang Baru',
        'username' => 'gudangbaru',
        'email' => 'gudangbaru@oasse.test',
        'password' => 'rahasia123',
        'roles' => ['Warehouse Staff'],
        'scope_level' => 'warehouse',
        'scope_warehouses' => [$warehouse->id],
        'is_active' => '1',
    ])->assertRedirect(route('team.users.index'));

    $user = User::withoutGlobalScopes()->where('email', 'gudangbaru@oasse.test')->firstOrFail();

    expect($user->hasRole('Warehouse Staff'))->toBeTrue();
    expect($user->scopes()->first()->warehouse_id)->toBe($warehouse->id);
    expect($user->can('inventory.receive'))->toBeTrue();
    expect($user->can('accounting.journal.post'))->toBeFalse();
});

it('menyimpan perubahan permission role', function (): void {
    $owner = $this->makeUser($this->company, 'Owner');
    $role = Role::where('company_id', $this->company->id)
        ->where('name', 'Viewer')
        ->firstOrFail();

    $this->actingAs($owner)->put(route('team.roles.update', $role), [
        'permissions' => ['sales.view', 'product.view'],
    ])->assertRedirect(route('team.roles.index'));

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['product.view', 'sales.view']);
});
