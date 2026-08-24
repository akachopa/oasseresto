@php
    use App\Modules\Core\Support\Money;

    $uninvoiced = $receipt->items->sum(fn ($item) => $item->uninvoicedBaseQuantity());
@endphp

<x-layouts.app :title="'Penerimaan '.$receipt->number">
    <x-page-header :title="$receipt->number"
                   :subtitle="$receipt->supplier?->name.' → '.$receipt->warehouse?->name"
                   :back="route('purchase.receipts.index')">
        <x-slot:actions>
            <x-status-badge :status="$receipt->status" />

            @if ($receipt->status->isEditable())
                <a href="{{ route('purchase.receipts.edit', $receipt) }}" wire:navigate class="btn-secondary">
                    <x-icon name="pencil" class="h-4 w-4" /> Ubah
                </a>

                <form method="POST" action="{{ route('purchase.receipts.post', $receipt) }}"
                      onsubmit="return confirm('Posting penerimaan dan tambahkan stok gudang?')">
                    @csrf
                    <button type="submit" class="btn-primary">
                        <x-icon name="check" class="h-4 w-4" /> Posting
                    </button>
                </form>
            @endif

            @if ($receipt->isPosted() && $uninvoiced > 0)
                @can('purchase.create')
                    <a href="{{ route('purchase.invoices.create', ['receipt' => $receipt->id]) }}" wire:navigate
                       class="btn-primary">
                        <x-icon name="receipt" class="h-4 w-4" /> Buat Invoice
                    </a>
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Tanggal" :value="$receipt->receipt_date->format('d/m/Y')"
               :hint="'Diterima '.($receipt->creator?->name ?? '-')" />
        <x-kpi label="Surat Jalan" :value="$receipt->supplier_do_number ?: '-'"
               :hint="$receipt->order?->number ? 'PO '.$receipt->order->number : 'tanpa PO'" />
        <x-kpi label="Belum Ditagih" :value="Money::quantity($uninvoiced)"
               :tone="$uninvoiced > 0 ? 'caution' : 'positive'" hint="dalam satuan dasar" />

        @can('inventory.valuation.view')
            <x-kpi label="Nilai Penerimaan" :value="Money::compact($receipt->total_value)" />
        @endcan
    </div>

    <div class="card card-pad mt-4 space-y-3">
        <p class="section-title">Barang</p>

        <div class="oasse-table-wrap">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Batch</th>
                        <th class="text-right">Diterima</th>
                        <th class="text-right">Ditolak</th>
                        <th class="text-right">Masuk Stok</th>
                        <th class="text-right">Ditagih</th>
                        <th class="text-right">Harga Pokok</th>
                        <th class="text-right">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($receipt->items as $item)
                        <tr>
                            <td class="font-medium">
                                {{ $item->product?->name }}
                                <span class="text-muted block font-mono text-xs">{{ $item->product?->sku }}</span>
                            </td>
                            <td class="font-mono text-xs">
                                {{ $item->batch?->batch_number ?: ($item->batch_number ?: '-') }}
                                @if ($item->expiry_date)
                                    <span class="text-muted block">exp {{ $item->expiry_date->format('d/m/y') }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                {{ Money::quantity($item->quantity) }} {{ $item->unit?->code }}
                            </td>
                            <td class="text-right">
                                @if ($item->rejected_base_quantity > 0)
                                    <span class="badge-warning">{{ Money::quantity($item->rejected_base_quantity) }}</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-right">
                                {{ Money::quantity($item->acceptedBaseQuantity()) }}
                                {{ $item->product?->baseUnit?->code }}
                            </td>
                            <td class="text-right">{{ Money::quantity($item->invoiced_base_quantity) }}</td>
                            <td class="text-right">{{ Money::rupiah($item->unit_cost) }}</td>
                            <td class="text-right font-medium">{{ Money::rupiah($item->total_cost) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($receipt->note)
            <p class="text-muted border-hairline border-t pt-3 text-sm">{{ $receipt->note }}</p>
        @endif
    </div>

    <div class="card card-pad mt-4 space-y-2">
        <p class="section-title">Jejak Dokumen</p>

        <dl class="divide-hairline divide-y text-sm">
            @foreach ([
                'Purchase Order' => $receipt->order?->number,
                'Diposting' => $receipt->posted_at?->format('d/m/Y H:i'),
            ] as $label => $value)
                <div class="flex items-start justify-between gap-3 py-2">
                    <dt class="text-muted">{{ $label }}</dt>
                    <dd class="text-right font-medium">{{ $value ?: '-' }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</x-layouts.app>
