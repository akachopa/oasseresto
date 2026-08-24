@php
    use App\Modules\Core\Support\Money;

    $salesHint = $yesterday->sales_amount > 0
        ? (($today->sales_amount - $yesterday->sales_amount) / $yesterday->sales_amount * 100)
        : null;
@endphp

<div class="space-y-4">
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <button type="button" wire:click="show('sales')" class="text-left">
            <x-kpi label="Omzet Hari Ini" :value="Money::compact($today->sales_amount)"
                   :hint="$today->sales_count.' invoice'.($salesHint !== null ? ' · '.Money::percent($salesHint).' vs kemarin' : '')"
                   :tone="$today->sales_amount >= $yesterday->sales_amount ? 'positive' : 'caution'"
                   icon="receipt" />
        </button>

        <button type="button" wire:click="show('month')" class="text-left">
            <x-kpi label="Omzet Bulan Ini" :value="Money::compact($month->sales_amount)"
                   :hint="$month->sales_count.' invoice'" icon="chart" />
        </button>

        @if ($showFinancial)
            <button type="button" wire:click="show('profit')" class="text-left">
                <x-kpi label="Laba Kotor MTD" :value="Money::compact($month->gross_profit)"
                       :hint="'Margin '.Money::percent($month->marginPercent())"
                       :tone="$month->gross_profit >= 0 ? 'positive' : 'negative'" />
            </button>

            <button type="button" wire:click="show('cash')" class="text-left">
                <x-kpi label="Saldo Kas" :value="Money::compact($today->cash_balance)"
                       :hint="'Masuk hari ini '.Money::compact($today->receipt_amount)"
                       :tone="$today->cash_balance >= 0 ? 'positive' : 'negative'"
                       icon="wallet" />
            </button>
        @else
            <x-kpi label="Antrian Picking" :value="(string) $today->pending_picking_count"
                   :href="route('delivery.picking.index')" icon="clipboard" />
            <x-kpi label="Menunggu Approval" :value="(string) $today->pending_approval_count"
                   :href="route('tasks.index')" icon="clock" />
        @endif
    </div>

    @if ($showFinancial)
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <button type="button" wire:click="show('ar')" class="text-left">
                <x-kpi label="Piutang" :value="Money::compact($today->ar_outstanding)"
                       icon="arrow-down" />
            </button>
            <button type="button" wire:click="show('overdue')" class="text-left">
                <x-kpi label="Piutang Jatuh Tempo" :value="Money::compact($today->ar_overdue)"
                       :tone="$today->ar_overdue > 0 ? 'negative' : 'positive'" />
            </button>
            <button type="button" wire:click="show('ap')" class="text-left">
                <x-kpi label="Hutang" :value="Money::compact($today->ap_outstanding)"
                       icon="arrow-up" />
            </button>
            <button type="button" wire:click="show('stock')" class="text-left">
                <x-kpi label="Nilai Persediaan" :value="Money::compact($today->inventory_value)"
                       :hint="$today->below_reorder_count.' SKU di bawah reorder'"
                       :tone="$today->below_reorder_count > 0 ? 'caution' : 'neutral'"
                       icon="boxes" />
            </button>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card card-pad lg:col-span-2">
            <p class="section-title mb-3">Attention Needed</p>

            @forelse ($alerts as $alert)
                <a @if ($alert->url) href="{{ $alert->url }}" wire:navigate @endif
                   class="border-hairline flex items-start gap-3 border-b py-2.5 last:border-0">
                    <span class="badge-{{ $alert->severity->color() ?? 'muted' }} mt-0.5 shrink-0">
                        {{ $alert->severity->label() }}
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-medium">{{ $alert->title }}</span>
                        <span class="text-muted block text-xs">{{ $alert->message }}</span>
                    </span>
                    @if ($alert->count > 1)
                        <span class="text-muted ml-auto text-xs tabular-nums">{{ $alert->count }}</span>
                    @endif
                </a>
            @empty
                <x-empty-state title="Tidak ada yang mendesak"
                               message="Stok, piutang, hutang, dan approval dalam kondisi terkendali."
                               icon="check" />
            @endforelse
        </div>

        <div class="card card-pad">
            <p class="section-title mb-3">Omzet 14 hari</p>

            @if ($trend === [])
                <p class="text-muted text-sm">Belum ada agregasi harian.</p>
            @else
                <ul class="space-y-1.5">
                    @foreach (array_reverse($trend) as $point)
                        <li class="flex items-center justify-between text-sm">
                            <span class="text-muted">{{ \Illuminate\Support\Carbon::parse($point['date'])->format('d/m') }}</span>
                            <span class="tabular-nums">{{ Money::compact($point['sales_amount']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    @if ($drill)
        <div class="card card-pad">
            <div class="mb-3 flex items-center justify-between gap-2">
                <p class="section-title">{{ $drill['title'] }}</p>
                <button type="button" wire:click="$set('focus', null)" class="btn-ghost text-xs">Tutup</button>
            </div>

            @if ($drill['rows'] === [])
                <p class="text-muted text-sm">{{ $drill['empty'] }}</p>
            @else
                <div class="oasse-table-wrap">
                    <table class="table-oasse">
                        <thead>
                            <tr>
                                @foreach ($drill['columns'] as $column)
                                    <th>{{ $column }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($drill['rows'] as $row)
                                <tr>
                                    @foreach ($row as $cell)
                                        <td>{!! $cell !!}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
