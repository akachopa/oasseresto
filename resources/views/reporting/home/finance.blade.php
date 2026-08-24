@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Home Keuangan">
    <x-page-header title="Home Keuangan"
                   subtitle="Posisi kas, piutang, hutang, dan biaya yang menunggu posting." />

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-kpi label="Saldo Kas" :value="Money::compact($cash)"
               :href="route('finance.cash.index')" icon="wallet"
               :tone="$cash >= 0 ? 'positive' : 'negative'" />
        <x-kpi label="Piutang" :value="Money::compact($ar)"
               :hint="'Jatuh tempo '.Money::compact($ar_overdue)"
               :href="route('finance.receivables.index')" />
        <x-kpi label="Hutang" :value="Money::compact($ap)"
               :hint="'Jatuh tempo '.Money::compact($ap_overdue)"
               :href="route('finance.payables.index')" />
        <x-kpi label="Penerimaan Hari Ini" :value="Money::compact($receipts_today)"
               :href="route('finance.receipts.index')" />
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <div class="card card-pad">
            <p class="section-title mb-3">Akun kas</p>
            @forelse ($accounts as $account)
                <a href="{{ route('finance.cash.detail', $account) }}" wire:navigate
                   class="border-hairline flex items-center justify-between border-b py-2 text-sm last:border-0">
                    <span>{{ $account->name }}</span>
                    <span class="tabular-nums">{{ Money::compact($account->balance) }}</span>
                </a>
            @empty
                <p class="text-muted text-sm">Belum ada akun kas.</p>
            @endforelse
        </div>

        <div class="card card-pad">
            <p class="section-title mb-3">Piutang jatuh tempo</p>
            @forelse ($overdue_ar as $row)
                <div class="border-hairline flex items-center justify-between border-b py-2 text-sm last:border-0">
                    <span class="truncate">{{ $row->customer?->name }}</span>
                    <span class="tabular-nums">{{ Money::compact($row->outstanding_amount) }}</span>
                </div>
            @empty
                <p class="text-muted text-sm">Tidak ada piutang jatuh tempo.</p>
            @endforelse
        </div>

        <div class="card card-pad">
            <p class="section-title mb-3">Hutang jatuh tempo</p>
            @forelse ($overdue_ap as $row)
                <div class="border-hairline flex items-center justify-between border-b py-2 text-sm last:border-0">
                    <span class="truncate">{{ $row->supplier?->name }}</span>
                    <span class="tabular-nums">{{ Money::compact($row->outstanding_amount) }}</span>
                </div>
            @empty
                <p class="text-muted text-sm">Tidak ada hutang jatuh tempo.</p>
            @endforelse
        </div>
    </div>
</x-layouts.app>
