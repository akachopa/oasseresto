@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Akuntansi">
    <x-page-header title="Home Accounting"
                   subtitle="Ringkasan jurnal bulan berjalan. Laporan lengkap ada di menu Akuntansi." />

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Neraca Saldo" :value="$trialBalance['balanced'] ? 'Seimbang' : 'Belum'"
               :tone="$trialBalance['balanced'] ? 'positive' : 'negative'"
               :href="route('accounting.reports.trial-balance')" icon="chart" />
        <x-kpi label="Debit" :value="Money::compact($trialBalance['total_debit'])" />
        <x-kpi label="Kredit" :value="Money::compact($trialBalance['total_credit'])" />
        <x-kpi label="Laba Bersih" :value="Money::compact($profitLoss['net_income'])"
               :tone="$profitLoss['net_income'] >= 0 ? 'positive' : 'negative'"
               :href="route('accounting.reports.profit-loss')" />
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <a href="{{ route('accounting.journals.index') }}" class="card card-pad hover:border-brand-400">Jurnal</a>
        <a href="{{ route('accounting.ledger') }}" class="card card-pad hover:border-brand-400">Buku Besar</a>
        <a href="{{ route('accounting.reports.balance-sheet') }}" class="card card-pad hover:border-brand-400">Neraca</a>
        <a href="{{ route('accounting.periods.index') }}" class="card card-pad hover:border-brand-400">Periode</a>
    </div>
</x-layouts.app>
