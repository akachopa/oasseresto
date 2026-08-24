@php
    use App\Modules\Core\Enums\DocumentStatus;
    use App\Modules\Core\Support\Money;

    $outstanding = $order->outstandingBaseQuantity();
@endphp

<x-layouts.app :title="'Sales Order '.$order->number">
    <x-page-header :title="$order->number"
                   :subtitle="$order->customer?->name.' · '.$order->warehouse?->name"
                   :back="route('sales.orders.index')">
        <x-slot:actions>
            <x-status-badge :status="$order->status" />

            @if ($order->status->isEditable())
                @can('sales.edit')
                    <a href="{{ route('sales.orders.edit', $order) }}" wire:navigate class="btn-secondary">
                        <x-icon name="pencil" class="h-4 w-4" /> Ubah
                    </a>
                @endcan

                @can('sales.create')
                    <form method="POST" action="{{ route('sales.orders.submit', $order) }}">
                        @csrf
                        <button type="submit" class="btn-primary">
                            <x-icon name="check" class="h-4 w-4" /> Ajukan
                        </button>
                    </form>
                @endcan
            @endif

            @if ($order->status === DocumentStatus::Submitted)
                @can('sales.approve')
                    <form method="POST" action="{{ route('sales.orders.approve', $order) }}">
                        @csrf
                        <button type="submit" class="btn-primary">
                            <x-icon name="check" class="h-4 w-4" /> Setujui
                        </button>
                    </form>
                @endcan
            @endif

            @if ($order->isDeliverable() && $outstanding > 0)
                @can('delivery.create')
                    <a href="{{ route('delivery.orders.create', ['order' => $order->id]) }}" wire:navigate
                       class="btn-primary">
                        <x-icon name="truck" class="h-4 w-4" /> Buat Surat Jalan
                    </a>
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Tanggal Order" :value="$order->order_date->format('d/m/Y')"
               :hint="$order->payment_term->label()" />
        <x-kpi label="Total" :value="Money::compact($order->total)"
               :hint="'margin '.Money::percent($order->marginPercent())" />
        <x-kpi label="Belum Terkirim" :value="Money::quantity($outstanding)"
               :tone="$outstanding > 0 ? 'caution' : 'positive'" hint="dalam satuan dasar" />
        <x-kpi label="Kondisi Kredit" :value="$credit->label()"
               :tone="$credit->isClear() ? 'positive' : ($credit->isBlocked() ? 'negative' : 'caution')"
               :hint="'sisa limit '.Money::compact($credit->available)" />
    </div>

    @if ($order->credit_note || $order->margin_note)
        <div class="card card-pad border-l-caution mt-4 border-l-4 space-y-1">
            <p class="section-title">Catatan Pemeriksaan</p>
            @if ($order->credit_note)
                <p class="text-sm">{{ $order->credit_note }}</p>
            @endif
            @if ($order->margin_note)
                <p class="text-sm">{{ $order->margin_note }}</p>
            @endif
        </div>
    @endif

    @if ($order->rejection_reason)
        <div class="card card-pad border-l-negative mt-4 border-l-4">
            <p class="section-title">Alasan Penolakan</p>
            <p class="mt-1 text-sm">{{ $order->rejection_reason }}</p>
        </div>
    @endif

    <div class="card card-pad mt-4 space-y-3">
        <p class="section-title">Barang Dipesan</p>

        <div class="oasse-table-wrap">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th class="text-right">Dipesan</th>
                        <th class="text-right">Direservasi</th>
                        <th class="text-right">Terkirim</th>
                        <th class="text-right">Ditagih</th>
                        <th class="text-right">Harga</th>
                        <th class="text-right">Margin</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td class="font-medium">
                                {{ $item->product?->name }}
                                <span class="text-muted block font-mono text-xs">{{ $item->product?->sku }}</span>
                            </td>
                            <td class="text-right">
                                {{ Money::quantity($item->quantity) }} {{ $item->unit?->code }}
                                <span class="text-muted block text-xs">
                                    {{ Money::quantity($item->base_quantity) }} {{ $item->product?->baseUnit?->code }}
                                </span>
                            </td>
                            <td class="text-right">{{ Money::quantity($item->reserved_base_quantity) }}</td>
                            <td class="text-right">
                                {{ Money::quantity($item->delivered_base_quantity) }}
                                @if ($item->outstandingBaseQuantity() > 0 && ! $order->status->isEditable())
                                    <span class="badge-warning ml-1">
                                        sisa {{ Money::quantity($item->outstandingBaseQuantity()) }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-right">{{ Money::quantity($item->invoiced_base_quantity) }}</td>
                            <td class="text-right">{{ Money::rupiah($item->unit_price) }}</td>
                            <td class="text-right">{{ Money::percent($item->margin_percent) }}</td>
                            <td class="text-right font-medium">{{ Money::rupiah($item->line_total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <dl class="border-hairline ml-auto w-full max-w-xs space-y-1 border-t pt-3 text-sm sm:w-72">
            <div class="flex items-center justify-between">
                <dt class="text-muted">Subtotal</dt>
                <dd>{{ Money::rupiah($order->subtotal) }}</dd>
            </div>
            <div class="flex items-center justify-between">
                <dt class="text-muted">Pajak</dt>
                <dd>{{ Money::rupiah($order->tax_amount) }}</dd>
            </div>
            <div class="flex items-center justify-between">
                <dt class="text-muted">Biaya Kirim</dt>
                <dd>{{ Money::rupiah($order->shipping_cost) }}</dd>
            </div>
            <div class="border-hairline flex items-center justify-between border-t pt-1 text-base font-semibold">
                <dt>Total</dt>
                <dd>{{ Money::rupiah($order->total) }}</dd>
            </div>

            @can('product.cost.view')
                <div class="flex items-center justify-between">
                    <dt class="text-muted">Estimasi HPP</dt>
                    <dd>{{ Money::rupiah($order->estimated_cost) }}</dd>
                </div>
            @endcan
        </dl>

        @if ($order->note)
            <p class="text-muted border-hairline border-t pt-3 text-sm">{{ $order->note }}</p>
        @endif
    </div>

    @include('purchase.partials.approval-trail', ['approval' => $approval])

    @if ($order->deliveries->isNotEmpty() || $order->invoices->isNotEmpty())
        <div class="card card-pad mt-4 space-y-3">
            <p class="section-title">Dokumen Lanjutan</p>

            <div class="oasse-table-wrap">
                <table class="table-oasse">
                    <thead>
                        <tr>
                            <th>Jenis</th>
                            <th>Nomor</th>
                            <th>Tanggal</th>
                            <th class="text-right">Nilai</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->deliveries as $delivery)
                            <tr>
                                <td>Surat Jalan</td>
                                <td>
                                    <a href="{{ route('delivery.orders.detail', $delivery) }}" wire:navigate
                                       class="font-mono text-xs hover:text-brand-600">{{ $delivery->number }}</a>
                                </td>
                                <td>{{ $delivery->delivery_date->format('d/m/Y') }}</td>
                                <td class="text-right">{{ Money::rupiah($delivery->total_value) }}</td>
                                <td><x-status-badge :status="$delivery->status" /></td>
                            </tr>
                        @endforeach

                        @foreach ($order->invoices as $invoice)
                            <tr>
                                <td>Invoice</td>
                                <td>
                                    <a href="{{ route('sales.invoices.detail', $invoice) }}" wire:navigate
                                       class="font-mono text-xs hover:text-brand-600">{{ $invoice->number }}</a>
                                </td>
                                <td>{{ $invoice->invoice_date->format('d/m/Y') }}</td>
                                <td class="text-right">{{ Money::rupiah($invoice->total) }}</td>
                                <td><x-status-badge :status="$invoice->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @can('sales.approve')
        @if ($order->status === DocumentStatus::Submitted || $order->isDeliverable())
            <div class="card card-pad mt-4 space-y-4">
                @if ($order->status === DocumentStatus::Submitted)
                    <div class="space-y-2">
                        <p class="section-title">Tolak Order</p>

                        <form method="POST" action="{{ route('sales.orders.reject', $order) }}"
                              class="flex flex-wrap items-end gap-3">
                            @csrf

                            <div class="min-w-64 flex-1">
                                <label class="label" for="reason">Alasan</label>
                                <input id="reason" name="reason" class="input" required
                                       placeholder="Contoh: customer masih punya tagihan jatuh tempo">
                            </div>

                            <button type="submit" class="btn-danger">
                                <x-icon name="x" class="h-4 w-4" /> Tolak
                            </button>
                        </form>
                    </div>
                @endif

                @if ($order->isDeliverable())
                    <div class="space-y-2">
                        <p class="section-title">Tutup Order</p>
                        <p class="text-muted text-sm">
                            Menutup order melepas reservasi stok sehingga barangnya bisa dijual ke customer lain.
                        </p>

                        <form method="POST" action="{{ route('sales.orders.close', $order) }}"
                              onsubmit="return confirm('Tutup order dan lepas reservasi stok?')">
                            @csrf
                            <button type="submit" class="btn-secondary">
                                <x-icon name="check" class="h-4 w-4" /> Tutup Order
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        @endif
    @endcan

    <div class="card card-pad mt-4 space-y-2">
        <p class="section-title">Jejak Dokumen</p>

        <dl class="divide-hairline divide-y text-sm">
            @foreach ([
                'Dari Penawaran' => $order->quotation?->number,
                'Salesman' => $order->salesman?->name,
                'Diajukan' => $order->submitted_at?->format('d/m/Y H:i'),
                'Disetujui' => $order->approved_at?->format('d/m/Y H:i'),
                'Penyetuju' => $order->approver?->name,
                'Ditutup' => $order->closed_at?->format('d/m/Y H:i'),
            ] as $label => $value)
                <div class="flex items-start justify-between gap-3 py-2">
                    <dt class="text-muted">{{ $label }}</dt>
                    <dd class="text-right font-medium">{{ $value ?: '-' }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</x-layouts.app>
