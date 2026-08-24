@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Home Pembelian">
    <x-page-header title="Home Pembelian"
                   subtitle="Reorder, PO terbuka, dan hutang yang perlu dijadwalkan." />

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <x-kpi label="Perlu Reorder" :value="(string) $reorder_count"
               :href="route('purchase.reorder.index')" icon="sparkles"
               :tone="$reorder_count > 0 ? 'caution' : 'positive'" />
        <x-kpi label="PO Terbuka" :value="(string) $open_po"
               :href="route('purchase.orders.index')" icon="document" />
        <x-kpi label="PO Terlambat" :value="(string) $late_po"
               :tone="$late_po > 0 ? 'negative' : 'positive'" />
        <x-kpi label="GR Draft" :value="(string) $draft_receipts"
               :href="route('purchase.receipts.index')" />
        <x-kpi label="Hutang Jatuh Tempo" :value="Money::compact($overdue_ap)"
               :href="route('finance.payables.index')"
               :tone="$overdue_ap > 0 ? 'negative' : 'positive'" />
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <div class="card card-pad">
            <p class="section-title mb-3">Purchase order terbuka</p>
            @forelse ($orders as $order)
                <a href="{{ route('purchase.orders.detail', $order) }}" wire:navigate
                   class="border-hairline flex items-center justify-between border-b py-2 text-sm last:border-0">
                    <span class="font-mono text-xs">{{ $order->number }}</span>
                    <span class="text-muted truncate">{{ $order->supplier?->name }}</span>
                    <span class="tabular-nums">{{ Money::compact($order->total) }}</span>
                </a>
            @empty
                <p class="text-muted text-sm">Tidak ada PO terbuka.</p>
            @endforelse
        </div>

        <div class="card card-pad">
            <p class="section-title mb-3">Rekomendasi reorder</p>
            @forelse ($suggestions as $row)
                <div class="border-hairline flex items-center justify-between border-b py-2 text-sm last:border-0">
                    <span class="truncate">{{ $row->name }}</span>
                    <span class="text-muted tabular-nums">{{ Money::quantity($row->suggested_quantity) }}</span>
                </div>
            @empty
                <p class="text-muted text-sm">Tidak ada rekomendasi reorder.</p>
            @endforelse
        </div>
    </div>
</x-layouts.app>
