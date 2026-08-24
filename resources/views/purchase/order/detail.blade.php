@php
    use App\Modules\Core\Enums\DocumentStatus;
    use App\Modules\Core\Support\Money;

    $outstanding = $order->outstandingBaseQuantity();
@endphp

<x-layouts.app :title="'Purchase Order '.$order->number">
    <x-page-header :title="$order->number"
                   :subtitle="$order->supplier?->name.' → '.$order->warehouse?->name"
                   :back="route('purchase.orders.index')">
        <x-slot:actions>
            <x-status-badge :status="$order->status" />

            <a href="{{ route('purchase.orders.print', $order) }}" target="_blank" class="btn-secondary">
                <x-icon name="document" class="h-4 w-4" /> Cetak
            </a>

            @if ($order->status->isEditable())
                @can('purchase.edit')
                    <a href="{{ route('purchase.orders.edit', $order) }}" wire:navigate class="btn-secondary">
                        <x-icon name="pencil" class="h-4 w-4" /> Ubah
                    </a>
                @endcan

                @can('purchase.create')
                    <form method="POST" action="{{ route('purchase.orders.submit', $order) }}">
                        @csrf
                        <button type="submit" class="btn-primary">
                            <x-icon name="check" class="h-4 w-4" /> Ajukan Approval
                        </button>
                    </form>
                @endcan
            @endif

            @if ($order->status === DocumentStatus::Submitted)
                @can('purchase.approve')
                    <form method="POST" action="{{ route('purchase.orders.approve', $order) }}">
                        @csrf
                        <button type="submit" class="btn-primary">
                            <x-icon name="check" class="h-4 w-4" /> Setujui
                        </button>
                    </form>
                @endcan
            @endif

            @if ($order->isReceivable())
                @can('inventory.receive')
                    <a href="{{ route('purchase.receipts.create', ['order' => $order->id]) }}" wire:navigate
                       class="btn-primary">
                        <x-icon name="inbox" class="h-4 w-4" /> Terima Barang
                    </a>
                @endcan
            @endif

            @if ($order->isInvoiceable())
                @can('purchase.create')
                    <a href="{{ route('purchase.invoices.create') }}" wire:navigate class="btn-secondary">
                        <x-icon name="receipt" class="h-4 w-4" /> Buat Invoice
                    </a>
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Tanggal PO" :value="$order->order_date->format('d/m/Y')"
               :hint="'Dibuat '.($order->creator?->name ?? '-')" />
        <x-kpi label="Perkiraan Datang" :value="$order->expected_date?->format('d/m/Y') ?? '-'"
               :hint="$order->payment_term->label()" />
        <x-kpi label="Total" :value="Money::compact($order->total)" />
        <x-kpi label="Belum Diterima" :value="Money::quantity($outstanding)"
               :tone="$outstanding > 0 ? 'caution' : 'positive'" hint="dalam satuan dasar" />
    </div>

    @if ($order->isBackorder())
        <div class="card card-pad border-l-caution mt-4 border-l-4">
            <p class="section-title">Backorder</p>
            <p class="mt-1 text-sm">
                Masih ada {{ Money::quantity($outstanding) }} satuan dasar yang belum dikirim supplier.
                Tutup PO bila sisa ini memang tidak akan dikirim.
            </p>
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
                        <th class="text-right">Diterima</th>
                        <th class="text-right">Ditagih</th>
                        <th class="text-right">Harga</th>
                        <th class="text-right">Disc</th>
                        <th class="text-right">Pajak</th>
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
                            <td class="text-right">
                                {{ Money::quantity($item->received_base_quantity) }}
                                @if ($item->outstandingBaseQuantity() > 0 && ! $order->status->isEditable())
                                    <span class="badge-warning ml-1">
                                        sisa {{ Money::quantity($item->outstandingBaseQuantity()) }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-right">{{ Money::quantity($item->invoiced_base_quantity) }}</td>
                            <td class="text-right">{{ Money::rupiah($item->unit_price) }}</td>
                            <td class="text-right">{{ Money::rupiah($item->discount_amount) }}</td>
                            <td class="text-right">{{ Money::rupiah($item->tax_amount) }}</td>
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
                <dt class="text-muted">Diskon</dt>
                <dd>{{ Money::rupiah($order->discount_amount) }}</dd>
            </div>
            <div class="flex items-center justify-between">
                <dt class="text-muted">Pajak</dt>
                <dd>{{ Money::rupiah($order->tax_amount) }}</dd>
            </div>
            <div class="flex items-center justify-between">
                <dt class="text-muted">Biaya Lain</dt>
                <dd>{{ Money::rupiah($order->other_cost) }}</dd>
            </div>
            <div class="border-hairline flex items-center justify-between border-t pt-1 text-base font-semibold">
                <dt>Total</dt>
                <dd>{{ Money::rupiah($order->total) }}</dd>
            </div>
        </dl>

        @if ($order->note || $order->terms)
            <p class="text-muted border-hairline border-t pt-3 text-sm">
                {{ $order->note }}
                @if ($order->terms)
                    <span class="block">Syarat: {{ $order->terms }}</span>
                @endif
            </p>
        @endif
    </div>

    @include('purchase.partials.approval-trail', ['approval' => $approval])

    @if ($order->receipts->isNotEmpty() || $order->invoices->isNotEmpty())
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
                        @foreach ($order->receipts as $receipt)
                            <tr>
                                <td>Penerimaan</td>
                                <td>
                                    <a href="{{ route('purchase.receipts.detail', $receipt) }}" wire:navigate
                                       class="font-mono text-xs hover:text-brand-600">{{ $receipt->number }}</a>
                                </td>
                                <td>{{ $receipt->receipt_date->format('d/m/Y') }}</td>
                                <td class="text-right">{{ Money::rupiah($receipt->total_value) }}</td>
                                <td><x-status-badge :status="$receipt->status" /></td>
                            </tr>
                        @endforeach

                        @foreach ($order->invoices as $invoice)
                            <tr>
                                <td>Invoice</td>
                                <td>
                                    <a href="{{ route('purchase.invoices.detail', $invoice) }}" wire:navigate
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

    @can('purchase.approve')
        @if ($order->status === DocumentStatus::Submitted || $order->isReceivable())
            <div class="card card-pad mt-4 space-y-4">
                @if ($order->status === DocumentStatus::Submitted)
                    <div class="space-y-2">
                        <p class="section-title">Tolak Purchase Order</p>

                        <form method="POST" action="{{ route('purchase.orders.reject', $order) }}"
                              class="flex flex-wrap items-end gap-3">
                            @csrf

                            <div class="min-w-64 flex-1">
                                <label class="label" for="reason">Alasan</label>
                                <input id="reason" name="reason" class="input" required
                                       placeholder="Contoh: harga di atas anggaran">
                            </div>

                            <button type="submit" class="btn-danger">
                                <x-icon name="x" class="h-4 w-4" /> Tolak
                            </button>
                        </form>
                    </div>
                @endif

                @if ($order->isReceivable())
                    <div class="space-y-2">
                        <p class="section-title">Tutup Purchase Order</p>
                        <p class="text-muted text-sm">
                            Menutup PO menghentikan backorder tanpa menghapus penerimaan yang sudah tercatat.
                        </p>

                        <form method="POST" action="{{ route('purchase.orders.close', $order) }}"
                              onsubmit="return confirm('Tutup PO dan anggap sisa barang tidak dikirim?')">
                            @csrf
                            <button type="submit" class="btn-secondary">
                                <x-icon name="check" class="h-4 w-4" /> Tutup PO
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
                'Dari Purchase Request' => $order->request?->number,
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
