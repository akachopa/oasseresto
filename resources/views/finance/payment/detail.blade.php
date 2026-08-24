@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app :title="'Pembayaran '.$payment->number">
    <x-page-header :title="$payment->number"
                   :subtitle="$payment->supplier?->name.' · '.$payment->methodLabel()"
                   :back="route('finance.payments.index')">
        <x-slot:actions>
            <x-status-badge :status="$payment->status" />

            @if ($payment->status->isEditable())
                <a href="{{ route('finance.payments.edit', $payment) }}" wire:navigate class="btn-secondary">
                    <x-icon name="pencil" class="h-4 w-4" /> Ubah
                </a>

                <form method="POST" action="{{ route('finance.payments.post', $payment) }}"
                      onsubmit="return confirm('Posting pembayaran dan kurangi saldo kas?')">
                    @csrf
                    <button type="submit" class="btn-primary">
                        <x-icon name="check" class="h-4 w-4" /> Posting
                    </button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Tanggal" :value="$payment->payment_date->format('d/m/Y')"
               :hint="'Dibuat '.($payment->creator?->name ?? '-')" />
        <x-kpi label="Nilai" :value="Money::compact($payment->amount)" :hint="$payment->reference" />
        <x-kpi label="Dialokasikan" :value="Money::compact($payment->allocated_amount)"
               :tone="$payment->unallocatedAmount() > 0 ? 'caution' : 'positive'"
               :hint="$payment->unallocatedAmount() > 0 ? 'sisa '.Money::compact($payment->unallocatedAmount()) : null" />
        <x-kpi label="Dibayar dari" :value="$payment->cashAccount?->name ?? '-'" />
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
                        <th class="text-right">Sisa Hutang</th>
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
                            <td colspan="6" class="text-muted py-6 text-center">Belum ada alokasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($payment->note)
            <p class="text-muted border-hairline border-t pt-3 text-sm">{{ $payment->note }}</p>
        @endif
    </div>

    <div class="card card-pad mt-4 space-y-2">
        <p class="section-title">Jejak Dokumen</p>

        <dl class="divide-hairline divide-y text-sm">
            @foreach ([
                'Dibuat oleh' => $payment->creator?->name,
                'Diposting' => $payment->posted_at?->format('d/m/Y H:i'),
            ] as $label => $value)
                <div class="flex items-start justify-between gap-3 py-2">
                    <dt class="text-muted">{{ $label }}</dt>
                    <dd class="text-right font-medium">{{ $value ?: '-' }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</x-layouts.app>
