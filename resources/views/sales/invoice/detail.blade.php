@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app :title="'Invoice '.$invoice->number">
    <x-page-header :title="$invoice->number"
                   :subtitle="$invoice->customer?->name.' · '.$invoice->payment_term->label()"
                   :back="route('sales.invoices.index')">
        <x-slot:actions>
            <x-status-badge :status="$invoice->status" />

            <a href="{{ route('sales.invoices.print', $invoice) }}" target="_blank" class="btn-secondary">
                <x-icon name="document" class="h-4 w-4" /> Cetak
            </a>

            @if ($invoice->status->isEditable())
                @can('sales.create')
                    <a href="{{ route('sales.invoices.edit', $invoice) }}" wire:navigate class="btn-secondary">
                        <x-icon name="pencil" class="h-4 w-4" /> Ubah
                    </a>
                @endcan

                @can('sales.post')
                    <form method="POST" action="{{ route('sales.invoices.post', $invoice) }}"
                          class="flex items-end gap-2">
                        @csrf

                        <div>
                            <label class="label" for="paid_amount">Dibayar</label>
                            <input id="paid_amount" name="paid_amount" type="number" step="0.01" min="0" value="0"
                                   class="input w-32 text-right">
                        </div>

                        <button type="submit" class="btn-primary">
                            <x-icon name="check" class="h-4 w-4" /> Posting
                        </button>
                    </form>
                @endcan
            @endif

            @if ($invoice->isPosted())
                @can('sales.return')
                    <a href="{{ route('sales.returns.create', ['invoice' => $invoice->id]) }}" wire:navigate
                       class="btn-secondary">
                        <x-icon name="arrow-down" class="h-4 w-4" /> Buat Retur
                    </a>
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Tanggal" :value="$invoice->invoice_date->format('d/m/Y')"
               :hint="$invoice->source === 'pos' ? 'transaksi kasir' : ($invoice->order?->number ? 'SO '.$invoice->order->number : null)" />
        <x-kpi label="Jatuh Tempo" :value="$invoice->due_date->format('d/m/Y')"
               :tone="$invoice->isOverdue() ? 'negative' : 'neutral'"
               :hint="$invoice->isOverdue() ? 'terlambat '.$invoice->daysOverdue().' hari' : null" />
        <x-kpi label="Total" :value="Money::compact($invoice->total)" />
        <x-kpi label="Sisa Piutang" :value="Money::compact($invoice->outstanding_amount)"
               :tone="$invoice->outstanding_amount > 0 ? 'caution' : 'positive'" />
    </div>

    <div class="card card-pad mt-4 space-y-3">
        <p class="section-title">Baris Tagihan</p>

        <div class="oasse-table-wrap">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th class="text-right">Kuantitas</th>
                        <th class="text-right">Harga</th>
                        <th class="text-right">Diskon</th>
                        <th class="text-right">Pajak</th>
                        <th class="text-right">Diretur</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->items as $item)
                        <tr>
                            <td class="font-medium">
                                {{ $item->product?->name }}
                                <span class="text-muted block font-mono text-xs">{{ $item->product?->sku }}</span>
                            </td>
                            <td class="text-right">
                                {{ Money::quantity($item->quantity) }} {{ $item->unit?->code }}
                            </td>
                            <td class="text-right">{{ Money::rupiah($item->unit_price) }}</td>
                            <td class="text-right">{{ Money::rupiah($item->discount_amount) }}</td>
                            <td class="text-right">{{ Money::rupiah($item->tax_amount) }}</td>
                            <td class="text-right">{{ Money::quantity($item->returned_base_quantity) }}</td>
                            <td class="text-right font-medium">{{ Money::rupiah($item->line_total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <dl class="border-hairline ml-auto w-full max-w-xs space-y-1 border-t pt-3 text-sm sm:w-72">
            <div class="flex items-center justify-between">
                <dt class="text-muted">Subtotal</dt>
                <dd>{{ Money::rupiah($invoice->subtotal) }}</dd>
            </div>
            <div class="flex items-center justify-between">
                <dt class="text-muted">Pajak</dt>
                <dd>{{ Money::rupiah($invoice->tax_amount) }}</dd>
            </div>
            <div class="flex items-center justify-between">
                <dt class="text-muted">Biaya Kirim</dt>
                <dd>{{ Money::rupiah($invoice->shipping_cost) }}</dd>
            </div>
            <div class="border-hairline flex items-center justify-between border-t pt-1 text-base font-semibold">
                <dt>Total</dt>
                <dd>{{ Money::rupiah($invoice->total) }}</dd>
            </div>
            <div class="flex items-center justify-between">
                <dt class="text-muted">Sudah Dibayar</dt>
                <dd>{{ Money::rupiah($invoice->paid_amount) }}</dd>
            </div>

            @can('product.cost.view')
                <div class="flex items-center justify-between">
                    <dt class="text-muted">HPP</dt>
                    <dd>{{ Money::rupiah($invoice->cost_of_goods) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-muted">Laba Kotor</dt>
                    <dd>{{ Money::rupiah($invoice->grossProfit()) }} ({{ Money::percent($invoice->marginPercent()) }})</dd>
                </div>
            @endcan
        </dl>

        @if ($invoice->note)
            <p class="text-muted border-hairline border-t pt-3 text-sm">{{ $invoice->note }}</p>
        @endif
    </div>

    <div class="card card-pad mt-4 space-y-2">
        <p class="section-title">Jejak Dokumen</p>

        <dl class="divide-hairline divide-y text-sm">
            @foreach ([
                'Sales Order' => $invoice->order?->number,
                'Surat Jalan' => $invoice->delivery?->number,
                'Shift Kasir' => $invoice->shift?->number,
                'Salesman' => $invoice->salesman?->name,
                'Diposting' => $invoice->posted_at?->format('d/m/Y H:i'),
            ] as $label => $value)
                <div class="flex items-start justify-between gap-3 py-2">
                    <dt class="text-muted">{{ $label }}</dt>
                    <dd class="text-right font-medium">{{ $value ?: '-' }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</x-layouts.app>
