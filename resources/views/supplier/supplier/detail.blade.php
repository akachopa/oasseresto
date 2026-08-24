@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app :title="$supplier->name">
    <x-page-header :title="$supplier->name" :subtitle="$supplier->code" :back="route('suppliers.index')">
        <x-slot:actions>
            @can('supplier.edit')
                <a href="{{ route('suppliers.edit', $supplier) }}" wire:navigate class="btn-secondary">
                    <x-icon name="pencil" class="h-4 w-4" /> Ubah
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Hutang Berjalan" :value="Money::compact($supplier->outstanding_amount)"
               :hint="$supplier->payment_term->label()" />

        <x-kpi label="Total Pembelian" :value="Money::compact($supplier->total_purchase)"
               :hint="$supplier->order_count.' PO'" />

        <x-kpi label="Ketepatan Kirim" :value="Money::percent($supplier->on_time_rate)"
               :tone="$supplier->on_time_rate >= 90 ? 'positive' : ($supplier->on_time_rate >= 75 ? 'caution' : 'negative')" />

        <x-kpi label="Lead Time Rata-rata"
               :value="number_format($supplier->average_lead_time, 1, ',', '.').' hari'"
               :hint="'Janji '.$supplier->lead_time_days.' hari'" />
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="card card-pad space-y-3">
            <p class="section-title">Kontak & Rekening</p>

            <dl class="divide-hairline divide-y text-sm">
                @foreach ([
                    'PIC' => $supplier->pic_name,
                    'Telepon' => $supplier->phone,
                    'WhatsApp' => $supplier->whatsapp,
                    'Email' => $supplier->email,
                    'Kota' => $supplier->city,
                    'NPWP' => $supplier->tax_number,
                    'Kode Pajak' => $supplier->taxCode?->name,
                    'Bank' => $supplier->bank_name,
                    'Rekening' => $supplier->bank_account,
                    'Atas Nama' => $supplier->bank_account_name,
                    'Minimum Order' => Money::rupiah($supplier->minimum_order_amount),
                ] as $label => $value)
                    <div class="flex items-start justify-between gap-3 py-2">
                        <dt class="text-muted">{{ $label }}</dt>
                        <dd class="text-right font-medium">{{ $value ?: '-' }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($supplier->address)
                <p class="text-muted border-hairline border-t pt-3 text-sm">{{ $supplier->address }}</p>
            @endif
        </div>

        <div class="space-y-4 lg:col-span-2">
            <div class="card card-pad space-y-3">
                <p class="section-title">Barang yang Dipasok</p>

                <div class="oasse-table-wrap">
                    <table class="table-oasse">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>SKU Supplier</th>
                                <th>Satuan</th>
                                <th class="text-right">Harga Terakhir</th>
                                <th class="text-right">Min Qty</th>
                                <th>Lead Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($supplier->products as $item)
                                <tr>
                                    <td class="font-medium">
                                        <a class="hover:text-brand-600" href="{{ route('products.detail', $item->product_id) }}">
                                            {{ $item->product?->name }}
                                        </a>
                                        @if ($item->is_preferred)
                                            <span class="badge-success ml-1">Utama</span>
                                        @endif
                                    </td>
                                    <td class="font-mono text-xs">{{ $item->supplier_sku ?: '-' }}</td>
                                    <td>{{ $item->unit?->code ?? '-' }}</td>
                                    <td class="text-right">{{ Money::rupiah($item->last_price) }}</td>
                                    <td class="text-right">{{ Money::quantity($item->minimum_quantity) }}</td>
                                    <td>{{ $item->lead_time_days }} hari</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted">Belum ada barang terdaftar.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card card-pad space-y-3">
                <p class="section-title">Riwayat Harga</p>

                <div class="oasse-table-wrap">
                    <table class="table-oasse">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Produk</th>
                                <th>Satuan</th>
                                <th class="text-right">Harga</th>
                                <th>Referensi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($history as $row)
                                <tr>
                                    <td>{{ $row->effective_date?->format('d/m/Y') }}</td>
                                    <td>{{ $row->product?->name }}</td>
                                    <td>{{ $row->unit?->code ?? '-' }}</td>
                                    <td class="text-right">{{ Money::rupiah($row->price) }}</td>
                                    <td class="text-muted text-xs">{{ $row->reference_number ?: $row->source }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted">Belum ada riwayat harga.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($supplier->note)
                <div class="card card-pad">
                    <p class="section-title mb-2">Catatan</p>
                    <p class="text-sm">{{ $supplier->note }}</p>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
