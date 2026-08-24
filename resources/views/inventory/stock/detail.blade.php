@php
    use App\Modules\Core\Support\Money;

    $totalQuantity = $balances->sum('quantity');
    $totalReserved = $balances->sum('reserved_quantity');
    $totalValue = $balances->sum('total_value');
@endphp

<x-layouts.app :title="'Kartu Stok '.$product->name">
    <x-page-header :title="$product->name" :subtitle="$product->sku.' · satuan dasar '.$product->baseUnit?->code"
                   :back="route('inventory.stock.index')">
        <x-slot:actions>
            @can('product.view')
                <a href="{{ route('products.detail', $product) }}" wire:navigate class="btn-secondary">
                    <x-icon name="eye" class="h-4 w-4" /> Detail Produk
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Total Saldo" :value="Money::quantity($totalQuantity)" :hint="$product->baseUnit?->code" />
        <x-kpi label="Tersedia" :value="Money::quantity($totalQuantity - $totalReserved)"
               :hint="Money::quantity($totalReserved).' dialokasikan'"
               :tone="($totalQuantity - $totalReserved) <= $product->reorder_point ? 'negative' : 'positive'" />
        <x-kpi label="Titik Reorder" :value="Money::quantity($product->reorder_point)"
               :hint="'Lead time '.$product->lead_time_days.' hari'" />

        @can('inventory.valuation.view')
            <x-kpi label="Nilai Persediaan" :value="Money::compact($totalValue)"
                   :hint="'HPP '.Money::rupiah($product->average_cost)" />
        @endcan
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="card card-pad space-y-3">
            <p class="section-title">Saldo per Gudang</p>

            @forelse ($balances as $balance)
                <div class="border-hairline flex items-center justify-between gap-3 rounded-lg border p-3">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ $balance->warehouse_name }}</p>
                        <p class="text-muted text-xs">
                            Tersedia {{ Money::quantity($balance->availableQuantity()) }}
                            @if ($balance->reserved_quantity > 0)
                                · {{ Money::quantity($balance->reserved_quantity) }} dialokasikan
                            @endif
                        </p>
                    </div>
                    <span class="font-semibold tabular-nums">{{ Money::quantity($balance->quantity) }}</span>
                </div>
            @empty
                <p class="text-muted text-sm">Belum ada saldo di gudang mana pun.</p>
            @endforelse
        </div>

        <div class="space-y-4 lg:col-span-2">
            @if ($product->track_batch)
                <div class="card card-pad space-y-3">
                    <p class="section-title">Batch Tersedia (urutan FEFO)</p>

                    <div class="oasse-table-wrap">
                        <table class="table-oasse">
                            <thead>
                                <tr>
                                    <th>Batch</th>
                                    <th>Gudang</th>
                                    <th>Kedaluwarsa</th>
                                    <th class="text-right">Sisa</th>
                                    <th class="text-right">HPP</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($batches as $batch)
                                    <tr>
                                        <td class="font-mono text-xs">{{ $batch->batch_number }}</td>
                                        <td>{{ $batch->warehouse_name }}</td>
                                        <td>
                                            @if ($batch->expiry_date === null)
                                                <span class="text-muted">-</span>
                                            @elseif ($batch->isExpired())
                                                <span class="badge-danger">{{ $batch->expiry_date->format('d/m/Y') }}</span>
                                            @elseif ($batch->isNearExpiry())
                                                <span class="badge-warning">{{ $batch->expiry_date->format('d/m/Y') }}</span>
                                            @else
                                                {{ $batch->expiry_date->format('d/m/Y') }}
                                            @endif
                                        </td>
                                        <td class="text-right">{{ Money::quantity($batch->quantity) }}</td>
                                        <td class="text-right">{{ Money::rupiah($batch->unit_cost) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-muted">Belum ada batch tersisa.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="card card-pad space-y-3">
                <p class="section-title">50 Pergerakan Terakhir</p>

                <div class="oasse-table-wrap">
                    <table class="table-oasse">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Jenis</th>
                                <th>Gudang</th>
                                <th class="text-right">Gerakan</th>
                                <th class="text-right">Saldo</th>
                                <th>Dokumen</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($movements as $movement)
                                <tr>
                                    <td class="whitespace-nowrap">{{ $movement->transaction_at->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <span class="badge-{{ $movement->base_quantity > 0 ? 'success' : 'warning' }}">
                                            {{ $movement->transaction_type->label() }}
                                        </span>
                                    </td>
                                    <td>{{ $movement->warehouse_name }}</td>
                                    <td class="text-right {{ $movement->base_quantity > 0 ? 'text-positive' : 'text-negative' }}">
                                        {{ $movement->base_quantity > 0 ? '+' : '' }}{{ Money::quantity($movement->base_quantity) }}
                                    </td>
                                    <td class="text-right">{{ Money::quantity($movement->balance_quantity) }}</td>
                                    <td class="text-muted font-mono text-xs">{{ $movement->document_number ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted">Belum ada pergerakan stok.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
