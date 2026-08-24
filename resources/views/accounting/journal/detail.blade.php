@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app :title="'Jurnal '.$entry->number">
    <x-page-header :title="$entry->number" :back="route('accounting.journals.index')"
                   :subtitle="$entry->description">
        <x-slot:actions>
            @can('accounting.journal.reverse')
                @if ($entry->isPosted() && ! $entry->reversal_of_id)
                    <form method="POST" action="{{ route('accounting.journals.reverse', $entry) }}"
                          onsubmit="return confirm('Balik jurnal ini? Koreksi akan tercatat sebagai jurnal baru.')">
                        @csrf
                        <button type="submit" class="btn-secondary">Reversal</button>
                    </form>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card card-pad space-y-2 text-sm lg:col-span-1">
            <p><span class="text-muted">Tanggal</span> {{ $entry->entry_date->format('d/m/Y') }}</p>
            <p><span class="text-muted">Status</span> <x-status-badge :status="$entry->status" /></p>
            <p><span class="text-muted">Sumber</span> {{ $entry->source === 'manual' ? 'Manual' : 'Sistem' }}</p>
            <p><span class="text-muted">Dokumen</span> {{ $entry->document_number ?: '-' }}</p>
            <p><span class="text-muted">Periode</span> {{ $entry->period?->label() ?? '-' }}</p>
            <p><span class="text-muted">Dibuat</span> {{ $entry->creator?->name ?? '-' }}</p>
        </div>

        <div class="card overflow-hidden lg:col-span-2">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>Akun</th>
                        <th>Memo</th>
                        <th class="text-right">Debit</th>
                        <th class="text-right">Kredit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entry->lines as $line)
                        <tr>
                            <td class="font-mono text-xs">{{ $line->account?->code }} {{ $line->account?->name }}</td>
                            <td>{{ $line->memo }}</td>
                            <td class="text-right">{{ $line->debit > 0 ? Money::rupiah($line->debit) : '-' }}</td>
                            <td class="text-right">{{ $line->credit > 0 ? Money::rupiah($line->credit) : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="font-medium">
                        <td colspan="2">Total</td>
                        <td class="text-right">{{ Money::rupiah($entry->total_debit) }}</td>
                        <td class="text-right">{{ Money::rupiah($entry->total_credit) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</x-layouts.app>
