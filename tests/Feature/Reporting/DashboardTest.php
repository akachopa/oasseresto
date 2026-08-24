<?php

declare(strict_types=1);

use App\Modules\Core\Support\Money;
use App\Modules\Reporting\Livewire\OwnerDashboard;
use App\Modules\Reporting\Services\MetricsAggregationService;
use Livewire\Livewire;

it('mengizinkan owner membuka dashboard, tugas, insight, dan laporan', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    $owner = $this->makeUser($company, 'Owner', $branch);

    buildReceivable($company->id, $warehouse->id, $branch->id);

    $pages = [
        route('dashboard'),
        route('tasks.index'),
        route('insight.index'),
        route('insight.cash-forecast'),
        route('insight.stock-movement'),
        route('insight.profitability'),
        route('reports.sales'),
        route('reports.purchase'),
        route('reports.inventory-valuation'),
        route('reports.ar-aging'),
        route('reports.ap-aging'),
        route('reports.profit-by-branch'),
        route('reports.profit-by-product'),
    ];

    foreach ($pages as $page) {
        $this->actingAs($owner)->get($page)->assertOk();
    }

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertSee('Omzet Hari Ini')
        ->assertSee('Attention Needed');
});

it('membaca KPI dashboard dari daily_business_metrics', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    $owner = $this->makeUser($company, 'Owner', $branch);

    ['invoice' => $invoice] = buildReceivable($company->id, $warehouse->id, $branch->id);

    app(MetricsAggregationService::class)->aggregateCompany($company->id);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(Money::compact($invoice->total));
});

it('menampilkan rincian drill-down saat kartu omzet diklik', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    $owner = $this->makeUser($company, 'Owner', $branch);

    ['invoice' => $invoice] = buildReceivable($company->id, $warehouse->id, $branch->id);

    Livewire::actingAs($owner)
        ->test(OwnerDashboard::class)
        ->call('show', 'sales')
        ->assertSee($invoice->number);
});

it('membuka home sesuai peran dan menolak peran lain', function (): void {
    ['company' => $company, 'branch' => $branch] = $this->bootCompany();

    $warehouse = $this->makeUser($company, 'Warehouse Staff', $branch);
    $purchasing = $this->makeUser($company, 'Purchasing', $branch);
    $salesman = $this->makeUser($company, 'Salesman', $branch);
    $finance = $this->makeUser($company, 'Finance', $branch);

    $this->actingAs($warehouse)->get(route('home.warehouse'))->assertOk()->assertSee('Home Gudang');
    $this->actingAs($purchasing)->get(route('home.purchasing'))->assertOk()->assertSee('Home Pembelian');
    $this->actingAs($salesman)->get(route('home.salesman'))->assertOk()->assertSee('Home Salesman');
    $this->actingAs($finance)->get(route('home.finance'))->assertOk()->assertSee('Home Keuangan');

    $this->actingAs($salesman)->get(route('home.warehouse'))->assertForbidden();
    $this->actingAs($warehouse)->get(route('home.finance'))->assertForbidden();
    $this->actingAs($salesman)->get(route('insight.index'))->assertForbidden();
});

it('mengarahkan warehouse staff ke home gudang setelah login', function (): void {
    ['company' => $company, 'branch' => $branch] = $this->bootCompany();
    $staff = $this->makeUser($company, 'Warehouse Staff', $branch);

    $this->post('/login', ['email' => $staff->email, 'password' => 'password'])
        ->assertRedirect(route('home.warehouse'));
});
