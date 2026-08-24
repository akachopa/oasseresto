@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Performa Supplier">
    <x-page-header title="Performa Supplier"
                   subtitle="Skor gabungan dari ketepatan pengiriman (60%) dan kualitas barang (40%)."
                   :back="route('suppliers.index')" />

    @if ($suppliers->where('order_count', '>', 0)->isEmpty())
        <x-empty-state title="Belum ada riwayat pembelian"
                       message="Performa terisi otomatis setelah ada penerimaan barang dari purchase order." />
    @else
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="card card-pad space-y-3">
                <p class="section-title">Supplier Terbaik</p>

                <div class="space-y-2">
                    @foreach ($best as $supplier)
                        <a href="{{ route('suppliers.detail', $supplier) }}" wire:navigate
                           class="border-hairline hover:bg-panel-soft flex items-center justify-between gap-3 rounded-lg border p-3 transition">
                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ $supplier->name }}</p>
                                <p class="text-muted text-xs">
                                    {{ $supplier->order_count }} PO · lead time
                                    {{ number_format($supplier->average_lead_time, 1, ',', '.') }} hari
                                </p>
                            </div>
                            <span class="badge-{{ $supplier->performanceColor() }}">
                                {{ Money::percent($supplier->performanceScore()) }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="card card-pad space-y-3">
                <p class="section-title">Perlu Perhatian</p>

                @if ($attention->isEmpty())
                    <p class="text-muted text-sm">Semua supplier berkinerja baik.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($attention as $supplier)
                            <a href="{{ route('suppliers.detail', $supplier) }}" wire:navigate
                               class="border-hairline hover:bg-panel-soft flex items-center justify-between gap-3 rounded-lg border p-3 transition">
                                <div class="min-w-0">
                                    <p class="truncate font-medium">{{ $supplier->name }}</p>
                                    <p class="text-muted text-xs">
                                        Tepat waktu {{ Money::percent($supplier->on_time_rate) }} ·
                                        kualitas {{ Money::percent($supplier->quality_rate) }}
                                    </p>
                                </div>
                                <span class="badge-{{ $supplier->performanceColor() }}">
                                    {{ $supplier->performanceLabel() }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="card card-pad mt-4">
            <p class="section-title mb-3">Semua Supplier</p>

            <div class="oasse-table-wrap">
                <table class="table-oasse">
                    <thead>
                        <tr>
                            <th>Supplier</th>
                            <th class="text-right">PO</th>
                            <th class="text-right">Total Pembelian</th>
                            <th class="text-right">Tepat Waktu</th>
                            <th class="text-right">Kualitas</th>
                            <th class="text-right">Lead Time</th>
                            <th>Skor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($suppliers as $supplier)
                            <tr>
                                <td class="font-medium">
                                    <a class="hover:text-brand-600" href="{{ route('suppliers.detail', $supplier) }}">
                                        {{ $supplier->name }}
                                    </a>
                                </td>
                                <td class="text-right">{{ $supplier->order_count }}</td>
                                <td class="text-right">{{ Money::compact($supplier->total_purchase) }}</td>
                                <td class="text-right">{{ Money::percent($supplier->on_time_rate) }}</td>
                                <td class="text-right">{{ Money::percent($supplier->quality_rate) }}</td>
                                <td class="text-right">{{ number_format($supplier->average_lead_time, 1, ',', '.') }} hari</td>
                                <td><span class="badge-{{ $supplier->performanceColor() }}">{{ $supplier->performanceLabel() }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-layouts.app>
