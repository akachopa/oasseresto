<?php

declare(strict_types=1);

use App\Modules\Auth\Support\PermissionRegistry;
use App\Modules\Auth\Support\RoleTemplate;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Core\Services\ScopeManager;

it('menampilkan halaman login', function (): void {
    $this->get('/login')->assertOk()->assertSee('Masuk ke OASSE');
});

it('menolak akses dashboard untuk guest', function (): void {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('mengizinkan owner masuk dan melihat dashboard', function (): void {
    ['company' => $company] = $this->bootCompany();
    $owner = $this->makeUser($company, 'Owner');

    $this->post('/login', ['email' => $owner->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->actingAs($owner)->get('/dashboard')->assertOk();
});

it('membatasi user pada company-nya lewat global scope', function (): void {
    ['company' => $company, 'branch' => $branch] = $this->bootCompany();

    $other = Company::withoutGlobalScopes()->create([
        'code' => 'OTH',
        'name' => 'PT Lain',
    ]);

    Branch::withoutGlobalScopes()->create([
        'company_id' => $other->id,
        'code' => 'OTH1',
        'name' => 'Cabang Lain',
    ]);

    app(ScopeManager::class)->setCompanyId($company->id);

    expect(Branch::pluck('code')->all())
        ->not->toContain('OTH1')
        ->toContain($branch->code);
});

it('menghasilkan nomor dokumen unik dan berurutan per periode', function (): void {
    ['company' => $company, 'branch' => $branch] = $this->bootCompany();

    $service = app(DocumentNumberService::class);
    $first = $service->next('sales_order', $branch->id, now(), $company->id);
    $second = $service->next('sales_order', $branch->id, now(), $company->id);

    expect($first)->toBe('SO/BKL/'.now()->format('Y').'/'.now()->format('m').'/00001');
    expect($second)->toEndWith('/00002');
});

it('memberikan permission sesuai template role', function (): void {
    ['company' => $company] = $this->bootCompany();

    $cashier = $this->makeUser($company, 'Cashier');
    $owner = $this->makeUser($company, 'Owner');

    expect($cashier->can('pos.sell'))->toBeTrue();
    expect($cashier->can('accounting.journal.post'))->toBeFalse();
    expect($owner->can('accounting.journal.post'))->toBeTrue();

    expect(RoleTemplate::resolve('Owner'))->toHaveCount(count(PermissionRegistry::all()));
});

it('membatasi scope lokasi user cabang', function (): void {
    ['company' => $company, 'branch' => $branch] = $this->bootCompany();

    $curup = Branch::withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->where('code', 'CRP')
        ->firstOrFail();

    $user = $this->makeUser($company, 'Salesman', $branch);

    $this->actingAs($user);
    app(ScopeManager::class)->reset();

    $scope = app(ScopeManager::class);

    expect($scope->branchIds())->toBe([$branch->id]);
    expect($scope->canAccessBranch($curup->id))->toBeFalse();
    expect($scope->canAccessBranch($branch->id))->toBeTrue();
});
