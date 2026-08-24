<?php

declare(strict_types=1);

use App\Modules\Core\Models\ImportJob;
use App\Modules\Customer\Models\Customer;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\UploadedFile;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    ['company' => $this->company, 'branch' => $this->branch] = $this->bootCompany();
});

it('membuka halaman import dan mengunduh template csv', function (): void {
    $owner = $this->makeUser($this->company, 'Owner');

    $this->actingAs($owner)->get(route('settings.import.index'))->assertOk();

    $response = $this->actingAs($owner)->get(route('settings.import.template', 'products'));
    $response->assertOk();
    expect($response->streamedContent())->toContain('sku');
});

it('menolak akses import untuk salesman dan kasir', function (): void {
    $salesman = $this->makeUser($this->company, 'Salesman', $this->branch);
    $cashier = $this->makeUser($this->company, 'Cashier', $this->branch);

    $this->actingAs($salesman)->get(route('settings.import.index'))->assertForbidden();
    $this->actingAs($cashier)->get(route('settings.import.index'))->assertForbidden();
});

it('mengimpor produk dari csv lewat antrian sinkron', function (): void {
    $owner = $this->makeUser($this->company, 'Owner');

    $csv = implode("\n", [
        'sku,name,category,brand,unit,base_price,reorder_point,barcode',
        'IMP-001,Beras Import,Sembako,Sania,PCS,15000,10,899111',
        'IMP-002,,Sembako,Sania,PCS,10000,5,',
    ]);

    $file = UploadedFile::fake()->createWithContent('produk.csv', $csv);

    $this->actingAs($owner)
        ->post(route('settings.import.store'), [
            'type' => 'products',
            'file' => $file,
        ])
        ->assertRedirect();

    $job = ImportJob::query()->latest('id')->firstOrFail();

    expect($job->status)->toBe('completed');
    expect($job->created_count)->toBe(1);
    expect($job->error_count)->toBe(1);

    $product = Product::where('sku', 'IMP-001')->firstOrFail();
    expect($product->name)->toBe('Beras Import');
    expect($product->base_price)->toBe(15000.0);
    expect($product->units()->where('is_base', true)->exists())->toBeTrue();

    expect(Activity::query()->where('description', 'like', 'Import%')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->get(route('settings.import.detail', $job))
        ->assertOk()
        ->assertSee('Selesai')
        ->assertSee('SKU dan nama wajib diisi');

    $this->actingAs($owner)
        ->postJson(route('settings.import.data'), ['draw' => 1, 'start' => 0, 'length' => 10])
        ->assertOk()
        ->assertJsonPath('recordsTotal', 1);
});

it('mengimpor customer dan supplier dari csv', function (): void {
    $owner = $this->makeUser($this->company, 'Owner');

    $customers = UploadedFile::fake()->createWithContent(
        'customer.csv',
        "code,name,phone,city,credit_limit,payment_term\nCUS-IMP,Toko Import,0812,Bengkulu,5000000,net_14\n",
    );

    $this->actingAs($owner)->post(route('settings.import.store'), [
        'type' => 'customers',
        'file' => $customers,
    ])->assertRedirect();

    $customer = Customer::where('code', 'CUS-IMP')->firstOrFail();
    expect($customer->name)->toBe('Toko Import');
    expect($customer->credit_limit)->toBe(5_000_000.0);

    $suppliers = UploadedFile::fake()->createWithContent(
        'supplier.csv',
        "code,name,phone,city,lead_time_days\nSUP-IMP,PT Import Sukses,0736,Bengkulu,5\n",
    );

    $this->actingAs($owner)->post(route('settings.import.store'), [
        'type' => 'suppliers',
        'file' => $suppliers,
    ])->assertRedirect();

    $supplier = Supplier::where('code', 'SUP-IMP')->firstOrFail();
    expect($supplier->name)->toBe('PT Import Sukses');
    expect($supplier->lead_time_days)->toBe(5);
});

it('mengunduh export csv dan xlsx untuk produk', function (): void {
    $owner = $this->makeUser($this->company, 'Owner');

    Product::create([
        'sku' => 'EXP-001',
        'name' => 'Produk Export',
        'base_unit_id' => Unit::where('code', 'PCS')->value('id'),
        'base_price' => 12_000,
    ]);

    $csv = $this->actingAs($owner)
        ->get(route('export.download', ['type' => 'products', 'format' => 'csv']));

    $csv->assertOk();
    expect($csv->streamedContent())->toContain('EXP-001')->toContain('Produk Export');

    $xlsx = $this->actingAs($owner)
        ->get(route('export.download', ['type' => 'products', 'format' => 'xlsx']));

    $xlsx->assertOk();
    expect($xlsx->headers->get('content-disposition'))->toContain('xlsx');
    expect(strlen($xlsx->streamedContent()))->toBeGreaterThan(0);
});

it('menolak export master data untuk salesman dan kasir', function (): void {
    $salesman = $this->makeUser($this->company, 'Salesman', $this->branch);
    $cashier = $this->makeUser($this->company, 'Cashier', $this->branch);

    $this->actingAs($salesman)
        ->get(route('export.download', ['type' => 'products', 'format' => 'csv']))
        ->assertForbidden();

    $this->actingAs($cashier)
        ->get(route('export.download', ['type' => 'customers', 'format' => 'xlsx']))
        ->assertForbidden();
});

it('membuka audit trail untuk owner', function (): void {
    $owner = $this->makeUser($this->company, 'Owner');

    $this->actingAs($owner)->get(route('team.audit.index'))->assertOk();

    $this->actingAs($owner)
        ->postJson(route('team.audit.data'), ['draw' => 1, 'start' => 0, 'length' => 10])
        ->assertOk()
        ->assertJsonStructure(['draw', 'recordsTotal', 'data']);
});

it('menjadwalkan agregasi, alert, jatuh tempo, dan snapshot horizon', function (): void {
    $commands = collect(app(Schedule::class)->events())
        ->map(fn ($event) => $event->command ?? '')
        ->implode(' ');

    expect($commands)
        ->toContain('oasse:aggregate-metrics')
        ->toContain('oasse:scan-alerts')
        ->toContain('oasse:refresh-overdue')
        ->toContain('horizon:snapshot');
});

it('menjalankan perintah refresh jatuh tempo', function (): void {
    $this->artisan('oasse:refresh-overdue')->assertSuccessful();
});
