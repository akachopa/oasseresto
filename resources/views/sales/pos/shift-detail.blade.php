@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app :title="'Shift '.$shift->number">
    <x-page-header :title="$shift->number"
                   :subtitle="($shift->cashier?->name ?? '-').' · '.$shift->warehouse?->name"
                   :back="route('pos.shift')">
        <x-slot:actions>
            <span class="badge-{{ $shift->isOpen() ? 'info' : 'success' }}">
                {{ $shift->isOpen() ? 'Terbuka' : 'Ditutup' }}
            </span>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Transaksi" :value="(string) $summary['transaction_count']"
               :hint="'dibuka '.$shift->opened_at->format('d/m/Y H:i')" />
        <x-kpi label="Penjualan" :value="Money::compact($summary['gross_sales'])" />
        <x-kpi label="Perkiraan Kas" :value="Money::compact($shift->expected_cash)"
               :hint="'modal awal '.Money::compact($shift->opening_cash)" />
        <x-kpi label="Selisih Kas" :value="Money::rupiah($shift->difference)"
               :tone="$shift->difference < 0 ? 'negative' : ($shift->difference > 0 ? 'caution' : 'positive')"
               :hint="$shift->counted_cash !== null ? 'dihitung '.Money::rupiah($shift->counted_cash) : 'belum dihitung'" />
    </div>

    <div class="card card-pad mt-4 space-y-2">
        <p class="section-title">Rekap Kas</p>

        <dl class="divide-hairline divide-y text-sm">
            @foreach ([
                'Penjualan Tunai' => Money::rupiah($shift->cash_sales),
                'Penjualan Non Tunai' => Money::rupiah($shift->non_cash_sales),
                'Penjualan Kredit' => Money::rupiah($shift->credit_sales),
                'Kas Masuk' => Money::rupiah($shift->cash_in),
                'Kas Keluar' => Money::rupiah($shift->cash_out),
                'Harga Pokok' => Money::rupiah($summary['cost_of_goods']),
                'Ditutup' => $shift->closed_at?->format('d/m/Y H:i') ?? '-',
            ] as $label => $value)
                <div class="flex items-center justify-between py-2">
                    <dt class="text-muted">{{ $label }}</dt>
                    <dd class="font-medium">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>

        @if ($shift->note)
            <p class="text-muted border-hairline border-t pt-3 text-sm">{{ $shift->note }}</p>
        @endif
    </div>

    <div class="card card-pad mt-4 space-y-3">
        <p class="section-title">Transaksi Shift Ini</p>

        <div class="oasse-table-wrap">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Customer</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Dibayar</th>
                        <th class="text-right">Sisa</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td>
                                <a href="{{ route('sales.invoices.detail', $invoice) }}" wire:navigate
                                   class="font-mono text-xs hover:text-brand-600">{{ $invoice->number }}</a>
                            </td>
                            <td>{{ $invoice->customer?->name }}</td>
                            <td class="text-right">{{ Money::rupiah($invoice->total) }}</td>
                            <td class="text-right">{{ Money::rupiah($invoice->paid_amount) }}</td>
                            <td class="text-right">{{ Money::rupiah($invoice->outstanding_amount) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-muted py-6 text-center">Belum ada transaksi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
