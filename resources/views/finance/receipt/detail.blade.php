@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app :title="'Penerimaan '.$receipt->number">
    <x-page-header :title="$receipt->number"
                   :subtitle="$receipt->customer?->name.' · '.$receipt->methodLabel()"
                   :back="route('finance.receipts.index')">
        <x-slot:actions>
            <x-status-badge :status="$receipt->status" />

            @if ($receipt->status->isEditable())
                <a href="{{ route('finance.receipts.edit', $receipt) }}" wire:navigate class="btn-secondary">
                    <x-icon name="pencil" class="h-4 w-4" /> Ubah
                </a>

                <form method="POST" action="{{ route('finance.receipts.post', $receipt) }}"
                      onsubmit="return confirm('Posting penerimaan dan tambahkan ke kas?')">
                    @csrf
                    <button type="submit" class="btn-primary">
                        <x-icon name="check" class="h-4 w-4" /> Posting
                    </button>
                </form>
            @endif

            @if ($receipt->requiresClearing() && $receipt->cleared_date === null)
                <form method="POST" action="{{ route('finance.receipts.clear', $receipt) }}">
                    @csrf
                    <button type="submit" class="btn-secondary">
                        <x-icon name="check" class="h-4 w-4" /> Tandai Cair
                    </button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Tanggal" :value="$receipt->receipt_date->format('d/m/Y')"
               :hint="'Ditagih '.($receipt->collector?->name ?? '-')" />
        <x-kpi label="Nilai" :value="Money::compact($receipt->amount)" :hint="$receipt->reference" />
        <x-kpi label="Dialokasikan" :value="Money::compact($receipt->allocated_amount)"
               :tone="$receipt->unallocatedAmount() > 0 ? 'caution' : 'positive'"
               :hint="$receipt->unallocatedAmount() > 0 ? 'sisa '.Money::compact($receipt->unallocatedAmount()) : null" />
        <x-kpi label="Masuk ke" :value="$receipt->cashAccount?->name ?? '-'"
               :hint="$receipt->requiresClearing() ? ($receipt->cleared_date?->format('d/m/Y') ?? 'belum cair') : null" />
    </div>

    <div class="card card-pad mt-4 space-y-3">
        <p class="section-title">Alokasi Pembayaran</p>

        <div class="oasse-table-wrap">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>Dokumen</th>
                        <th>Jatuh Tempo</th>
                        <th class="text-right">Dibayar</th>
                        <th class="text-right">Diskon</th>
                        <th class="text-right">Sisa Tagihan</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($allocations as $row)
                        <tr>
                            <td class="font-mono text-xs">{{ $row['target']?->document_number ?? '-' }}</td>
                            <td>{{ $row['target']?->due_date?->format('d/m/Y') ?? '-' }}</td>
                            <td class="text-right">{{ Money::rupiah($row['model']->amount) }}</td>
                            <td class="text-right">{{ Money::rupiah($row['model']->discount_amount) }}</td>
                            <td class="text-right">{{ Money::rupiah($row['target']?->outstanding_amount ?? 0) }}</td>
                            <td>
                                @if ($row['target'])
                                    <x-status-badge :status="$row['target']->status" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted py-6 text-center">
                                Belum ada alokasi; uang ini masih berupa titipan customer.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($receipt->note)
            <p class="text-muted border-hairline border-t pt-3 text-sm">{{ $receipt->note }}</p>
        @endif
    </div>

    <div class="card card-pad mt-4 space-y-2">
        <p class="section-title">Jejak Dokumen</p>

        <dl class="divide-hairline divide-y text-sm">
            @foreach ([
                'Dibuat oleh' => $receipt->creator?->name,
                'Diposting' => $receipt->posted_at?->format('d/m/Y H:i'),
                'Tanggal Cair' => $receipt->cleared_date?->format('d/m/Y'),
            ] as $label => $value)
                <div class="flex items-start justify-between gap-3 py-2">
                    <dt class="text-muted">{{ $label }}</dt>
                    <dd class="text-right font-medium">{{ $value ?: '-' }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</x-layouts.app>
