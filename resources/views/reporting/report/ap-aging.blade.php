@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Umur Hutang">
    <x-page-header title="Umur Hutang"
                   subtitle="Bucket umur mengikuti kebijakan kredit company." />

    <form method="GET" class="card card-pad mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="label" for="supplier">Supplier</label>
            <select id="supplier" name="supplier" class="input w-auto">
                <option value="">Semua supplier</option>
                @foreach ($suppliers as $id => $name)
                    <option value="{{ $id }}" @selected($supplierId === (int) $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-secondary">Tampilkan</button>
    </form>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
        @foreach ($buckets as $bucket)
            <x-kpi :label="$bucket['label']" :value="Money::compact($totals[$bucket['label']] ?? 0)"
                   :tone="$bucket['to'] === null ? 'negative' : 'neutral'" />
        @endforeach
    </div>

    <div class="card card-pad mt-4 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="section-title">Rincian per Supplier</p>
            <p class="text-sm font-semibold">Total {{ Money::rupiah($totals['total']) }}</p>
        </div>

        <div class="oasse-table-wrap">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>Supplier</th>
                        <th class="text-right">Dokumen</th>
                        <th class="text-right">Umur Tertua</th>
                        @foreach ($buckets as $bucket)
                            <th class="text-right">{{ $bucket['label'] }}</th>
                        @endforeach
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($groups as $group)
                        <tr>
                            <td class="font-medium">{{ $group['name'] }}</td>
                            <td class="text-right">{{ $group['documents'] }}</td>
                            <td class="text-right">{{ $group['oldest_days'] > 0 ? $group['oldest_days'].' hari' : '-' }}</td>
                            @foreach ($buckets as $bucket)
                                <td class="text-right">{{ Money::rupiah($group['buckets'][$bucket['label']] ?? 0) }}</td>
                            @endforeach
                            <td class="text-right font-medium">{{ Money::rupiah($group['total']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($buckets) + 4 }}" class="text-muted py-6 text-center">Tidak ada hutang berjalan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
