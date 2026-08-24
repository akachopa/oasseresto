@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app :title="'Retur '.$return->number">
    <x-page-header :title="$return->number"
                   :subtitle="$return->customer?->name.' · '.$return->reasonLabel()"
                   :back="route('sales.returns.index')">
        <x-slot:actions>
            <x-status-badge :status="$return->status" />

            @if ($return->status->isEditable())
                <a href="{{ route('sales.returns.edit', $return) }}" wire:navigate class="btn-secondary">
                    <x-icon name="pencil" class="h-4 w-4" /> Ubah
                </a>

                <form method="POST" action="{{ route('sales.returns.post', $return) }}"
                      onsubmit="return confirm('Posting retur dan sesuaikan stok serta piutang?')">
                    @csrf
                    <button type="submit" class="btn-primary">
                        <x-icon name="check" class="h-4 w-4" /> Posting
                    </button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Tanggal" :value="$return->return_date->format('d/m/Y')"
               :hint="'Dibuat '.($return->creator?->name ?? '-')" />
        <x-kpi label="Gudang" :value="$return->warehouse?->name ?? '-'"
               :hint="$return->restock ? 'barang masuk stok' : 'tidak masuk stok'" />
        <x-kpi label="Penyelesaian" :value="$return->settlementLabel()"
               :hint="$return->invoice?->number ? 'Invoice '.$return->invoice->number : 'tanpa invoice'" />
        <x-kpi label="Nilai Retur" :value="Money::compact($return->total)" />
    </div>

    <div class="card card-pad mt-4 space-y-3">
        <p class="section-title">Barang Dikembalikan</p>

        <div class="oasse-table-wrap">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Batch</th>
                        <th class="text-right">Kuantitas</th>
                        <th class="text-right">Harga</th>
                        <th class="text-right">Pajak</th>
                        <th class="text-right">Total</th>

                        @can('product.cost.view')
                            <th class="text-right">HPP</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach ($return->items as $item)
                        <tr>
                            <td class="font-medium">
                                {{ $item->product?->name }}
                                <span class="text-muted block font-mono text-xs">{{ $item->product?->sku }}</span>
                            </td>
                            <td class="font-mono text-xs">{{ $item->batch?->batch_number ?: '-' }}</td>
                            <td class="text-right">
                                {{ Money::quantity($item->quantity) }} {{ $item->unit?->code }}
                                <span class="text-muted block text-xs">
                                    {{ Money::quantity($item->base_quantity) }} {{ $item->product?->baseUnit?->code }}
                                </span>
                            </td>
                            <td class="text-right">{{ Money::rupiah($item->unit_price) }}</td>
                            <td class="text-right">{{ Money::rupiah($item->tax_amount) }}</td>
                            <td class="text-right font-medium">{{ Money::rupiah($item->line_total) }}</td>

                            @can('product.cost.view')
                                <td class="text-right">{{ Money::rupiah($item->unit_cost) }}</td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <dl class="border-hairline ml-auto w-full max-w-xs space-y-1 border-t pt-3 text-sm sm:w-72">
            <div class="flex items-center justify-between">
                <dt class="text-muted">Subtotal</dt>
                <dd>{{ Money::rupiah($return->subtotal) }}</dd>
            </div>
            <div class="flex items-center justify-between">
                <dt class="text-muted">Pajak</dt>
                <dd>{{ Money::rupiah($return->tax_amount) }}</dd>
            </div>
            <div class="border-hairline flex items-center justify-between border-t pt-1 text-base font-semibold">
                <dt>Total</dt>
                <dd>{{ Money::rupiah($return->total) }}</dd>
            </div>

            @can('product.cost.view')
                <div class="flex items-center justify-between">
                    <dt class="text-muted">HPP Retur</dt>
                    <dd>{{ Money::rupiah($return->cost_of_goods) }}</dd>
                </div>
            @endcan
        </dl>

        @if ($return->note)
            <p class="text-muted border-hairline border-t pt-3 text-sm">{{ $return->note }}</p>
        @endif
    </div>

    <div class="card card-pad mt-4 space-y-2">
        <p class="section-title">Jejak Dokumen</p>

        <dl class="divide-hairline divide-y text-sm">
            @foreach ([
                'Invoice Sumber' => $return->invoice?->number,
                'Diposting' => $return->posted_at?->format('d/m/Y H:i'),
            ] as $label => $value)
                <div class="flex items-start justify-between gap-3 py-2">
                    <dt class="text-muted">{{ $label }}</dt>
                    <dd class="text-right font-medium">{{ $value ?: '-' }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</x-layouts.app>
