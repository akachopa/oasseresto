@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Home Salesman">
    <x-page-header title="Home Salesman"
                   subtitle="Omzet, order berjalan, dan tagihan customer Anda." />

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-kpi label="Omzet Hari Ini" :value="Money::compact($sales_today)" icon="receipt" />
        <x-kpi label="Omzet Bulan Ini" :value="Money::compact($sales_month)" icon="chart" />
        <x-kpi label="Order Berjalan" :value="(string) $open_orders"
               :href="route('sales.orders.index')" />
        <x-kpi label="Tagihan Jatuh Tempo" :value="Money::compact($overdue_ar)"
               :href="route('finance.receivables.index')"
               :tone="$overdue_ar > 0 ? 'negative' : 'positive'" />
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <div class="card card-pad">
            <p class="section-title mb-3">Invoice terbaru</p>
            @forelse ($invoices as $invoice)
                <a href="{{ route('sales.invoices.detail', $invoice) }}" wire:navigate
                   class="border-hairline flex items-center justify-between border-b py-2 text-sm last:border-0">
                    <span class="font-mono text-xs">{{ $invoice->number }}</span>
                    <span class="tabular-nums">{{ Money::compact($invoice->total) }}</span>
                </a>
            @empty
                <p class="text-muted text-sm">Belum ada invoice.</p>
            @endforelse
        </div>

        <div class="card card-pad">
            <p class="section-title mb-3">Order berjalan</p>
            @forelse ($orders as $order)
                <a href="{{ route('sales.orders.detail', $order) }}" wire:navigate
                   class="border-hairline flex items-center justify-between border-b py-2 text-sm last:border-0">
                    <span class="font-mono text-xs">{{ $order->number }}</span>
                    <span class="text-muted truncate">{{ $order->customer?->name }}</span>
                </a>
            @empty
                <p class="text-muted text-sm">Tidak ada order berjalan.</p>
            @endforelse
        </div>

        <div class="card card-pad">
            <p class="section-title mb-3">Piutang jatuh tempo</p>
            @forelse ($receivables as $row)
                <div class="border-hairline flex items-center justify-between border-b py-2 text-sm last:border-0">
                    <span class="truncate">{{ $row->customer?->name }}</span>
                    <span class="tabular-nums">{{ Money::compact($row->outstanding_amount) }}</span>
                </div>
            @empty
                <p class="text-muted text-sm">Tidak ada tagihan jatuh tempo.</p>
            @endforelse
        </div>
    </div>
</x-layouts.app>
