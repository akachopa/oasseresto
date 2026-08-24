<x-layouts.app :title="$product->name">
    <x-page-header :title="$product->name" :subtitle="$product->sku" :back="route('products.index')">
        <x-slot:actions>
            @can('product.edit')
                <a href="{{ route('products.edit', $product) }}" wire:navigate class="btn-secondary">
                    <x-icon name="pencil" class="h-4 w-4" /> Ubah
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Harga Dasar" :value="\App\Modules\Core\Support\Money::rupiah($product->base_price)"
               :hint="$product->baseUnit?->code" />

        @can('product.cost.view')
            <x-kpi label="HPP Rata-rata" :value="\App\Modules\Core\Support\Money::rupiah($product->average_cost)"
                   :hint="'Terakhir beli '.\App\Modules\Core\Support\Money::rupiah($product->last_purchase_cost)" />
        @endcan

        <x-kpi label="Titik Reorder" :value="\App\Modules\Core\Support\Money::quantity($product->reorder_point)"
               :hint="'Lead time '.$product->lead_time_days.' hari'" />

        <x-kpi label="Status" :value="$product->is_active ? 'Aktif' : 'Nonaktif'"
               :tone="$product->is_active ? 'positive' : 'negative'"
               :hint="$product->is_stocked ? 'Barang stok' : 'Non stok'" />
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="card card-pad space-y-3 lg:col-span-1">
            <p class="section-title">Informasi</p>

            <dl class="divide-hairline divide-y text-sm">
                @foreach ([
                    'Kategori' => $product->category?->name,
                    'Brand' => $product->brand?->name,
                    'Barcode' => $product->barcode,
                    'Kode Pajak' => $product->taxCode?->name,
                    'Satuan Beli' => $product->purchaseUnit?->code ?? $product->baseUnit?->code,
                    'Satuan Jual' => $product->salesUnit?->code ?? $product->baseUnit?->code,
                    'Batch' => $product->track_batch ? 'Dilacak' : 'Tidak',
                    'Kedaluwarsa' => $product->track_expiry ? 'Dilacak' : 'Tidak',
                    'Margin Minimum' => \App\Modules\Core\Support\Money::percent($product->minimumMargin()),
                ] as $label => $value)
                    <div class="flex items-start justify-between gap-3 py-2">
                        <dt class="text-muted">{{ $label }}</dt>
                        <dd class="text-right font-medium">{{ $value ?: '-' }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($product->description)
                <p class="text-muted border-hairline border-t pt-3 text-sm">{{ $product->description }}</p>
            @endif
        </div>

        <div class="space-y-4 lg:col-span-2">
            <div class="card card-pad space-y-3">
                <p class="section-title">Satuan & Konversi</p>

                <div class="oasse-table-wrap">
                    <table class="table-oasse">
                        <thead>
                            <tr>
                                <th>Satuan</th>
                                <th>Isi ke Satuan Dasar</th>
                                <th>Barcode</th>
                                <th>Dipakai</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($product->units->sortByDesc('is_base') as $unit)
                                <tr>
                                    <td class="font-medium">
                                        {{ $unit->unit?->code }}
                                        @if ($unit->is_base)
                                            <span class="badge-info ml-1">Dasar</span>
                                        @endif
                                    </td>
                                    <td>{{ \App\Modules\Core\Support\Money::quantity($unit->conversion_to_base) }}</td>
                                    <td class="font-mono text-xs">{{ $unit->barcode ?: '-' }}</td>
                                    <td class="text-muted text-xs">
                                        {{ collect([$unit->allow_purchase ? 'Beli' : null, $unit->allow_sales ? 'Jual' : null])->filter()->join(', ') ?: '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">Belum ada satuan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card card-pad space-y-3">
                <p class="section-title">Harga per Level</p>

                <div class="oasse-table-wrap">
                    <table class="table-oasse">
                        <thead>
                            <tr>
                                <th>Level Harga</th>
                                <th>Satuan</th>
                                <th class="text-right">Harga</th>
                                @can('product.cost.view')
                                    <th class="text-right">Margin</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($product->prices as $price)
                                @php
                                    $conversion = $product->conversionFor((int) $price->unit_id);
                                    $cost = $product->average_cost * $conversion;
                                    $margin = $price->price > 0 ? ($price->price - $cost) / $price->price * 100 : 0;
                                @endphp
                                <tr>
                                    <td class="font-medium">{{ $price->priceLevel?->name }}</td>
                                    <td>{{ $price->unit?->code }}</td>
                                    <td class="text-right">{{ \App\Modules\Core\Support\Money::rupiah($price->price) }}</td>
                                    @can('product.cost.view')
                                        <td class="text-right {{ $margin < $product->minimumMargin() ? 'text-negative font-medium' : '' }}">
                                            {{ \App\Modules\Core\Support\Money::percent($margin) }}
                                        </td>
                                    @endcan
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">Belum ada harga level.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @can('supplier.view')
                <div class="card card-pad space-y-3">
                    <p class="section-title">Supplier</p>

                    <div class="oasse-table-wrap">
                        <table class="table-oasse">
                            <thead>
                                <tr>
                                    <th>Supplier</th>
                                    <th>SKU Supplier</th>
                                    <th>Satuan</th>
                                    <th class="text-right">Harga Terakhir</th>
                                    <th>Lead Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($suppliers as $item)
                                    <tr>
                                        <td class="font-medium">
                                            <a class="hover:text-brand-600" href="{{ route('suppliers.detail', $item->supplier_id) }}">
                                                {{ $item->supplier?->name }}
                                            </a>
                                            @if ($item->is_preferred)
                                                <span class="badge-success ml-1">Utama</span>
                                            @endif
                                        </td>
                                        <td class="font-mono text-xs">{{ $item->supplier_sku ?: '-' }}</td>
                                        <td>{{ $item->unit?->code ?? $product->baseUnit?->code }}</td>
                                        <td class="text-right">{{ \App\Modules\Core\Support\Money::rupiah($item->last_price) }}</td>
                                        <td>{{ $item->lead_time_days }} hari</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-muted">Belum ada supplier terdaftar.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endcan
        </div>
    </div>
</x-layouts.app>
