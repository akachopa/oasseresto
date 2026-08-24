@php
    use App\Modules\Core\Enums\DocumentStatus;
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app :title="'Biaya '.$expense->number">
    <x-page-header :title="$expense->number"
                   :subtitle="$expense->category?->name.' · '.($expense->payee ?: 'tanpa penerima')"
                   :back="route('finance.expenses.index')">
        <x-slot:actions>
            <x-status-badge :status="$expense->status" />

            @if ($expense->status->isEditable())
                @can('finance.expense.create')
                    <a href="{{ route('finance.expenses.edit', $expense) }}" wire:navigate class="btn-secondary">
                        <x-icon name="pencil" class="h-4 w-4" /> Ubah
                    </a>

                    <form method="POST" action="{{ route('finance.expenses.submit', $expense) }}">
                        @csrf
                        <button type="submit" class="btn-primary">
                            <x-icon name="check" class="h-4 w-4" /> Ajukan
                        </button>
                    </form>
                @endcan
            @endif

            @if ($expense->status === DocumentStatus::Submitted)
                @can('finance.expense.approve')
                    <form method="POST" action="{{ route('finance.expenses.approve', $expense) }}">
                        @csrf
                        <button type="submit" class="btn-primary">
                            <x-icon name="check" class="h-4 w-4" /> Setujui
                        </button>
                    </form>
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Tanggal" :value="$expense->expense_date->format('d/m/Y')"
               :hint="'Diinput '.($expense->creator?->name ?? '-')" />
        <x-kpi label="Nilai" :value="Money::compact($expense->amount)"
               :hint="$expense->tax_amount > 0 ? 'pajak '.Money::compact($expense->tax_amount) : null" />
        <x-kpi label="Total Dibayar" :value="Money::compact($expense->total)" />
        <x-kpi label="Sumber Dana" :value="$expense->cashAccount?->name ?? 'belum dipilih'"
               :hint="$expense->costCenter?->name" />
    </div>

    @if ($expense->rejection_reason)
        <div class="card card-pad border-l-negative mt-4 border-l-4">
            <p class="section-title">Alasan Penolakan</p>
            <p class="mt-1 text-sm">{{ $expense->rejection_reason }}</p>
        </div>
    @endif

    <div class="card card-pad mt-4 space-y-2">
        <p class="section-title">Rincian</p>

        <dl class="divide-hairline divide-y text-sm">
            @foreach ([
                'Kategori' => $expense->category?->name,
                'Referensi' => $expense->reference,
                'Supplier' => $expense->supplier?->name,
                'Keterangan' => $expense->description,
                'Diajukan' => $expense->submitted_at?->format('d/m/Y H:i'),
                'Disetujui' => $expense->approved_at?->format('d/m/Y H:i'),
                'Penyetuju' => $expense->approver?->name,
                'Dibayar' => $expense->posted_at?->format('d/m/Y H:i'),
            ] as $label => $value)
                <div class="flex items-start justify-between gap-3 py-2">
                    <dt class="text-muted">{{ $label }}</dt>
                    <dd class="text-right font-medium">{{ $value ?: '-' }}</dd>
                </div>
            @endforeach
        </dl>
    </div>

    @include('purchase.partials.approval-trail', ['approval' => $approval])

    @if ($expense->isPayable())
        @can('finance.expense.post')
            <form method="POST" action="{{ route('finance.expenses.post', $expense) }}"
                  class="card card-pad mt-4 space-y-3">
                @csrf
                <p class="section-title">Bayarkan Biaya</p>

                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="label" for="cash_account_id">Kas/Bank Pembayar</label>
                        <select id="cash_account_id" name="cash_account_id" class="input w-auto" required>
                            <option value="">Pilih akun</option>
                            @foreach ($accounts as $id => $name)
                                <option value="{{ $id }}" @selected((int) $expense->cash_account_id === (int) $id)>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="btn-primary">
                        <x-icon name="wallet" class="h-4 w-4" /> Bayar {{ Money::rupiah($expense->total) }}
                    </button>
                </div>
            </form>
        @endcan
    @endif

    @if ($expense->status === DocumentStatus::Submitted)
        @can('finance.expense.approve')
            <div class="card card-pad mt-4 space-y-2">
                <p class="section-title">Tolak Biaya</p>

                <form method="POST" action="{{ route('finance.expenses.reject', $expense) }}"
                      class="flex flex-wrap items-end gap-3">
                    @csrf

                    <div class="min-w-64 flex-1">
                        <label class="label" for="reason">Alasan</label>
                        <input id="reason" name="reason" class="input" required
                               placeholder="Contoh: bukti pendukung belum lengkap">
                    </div>

                    <button type="submit" class="btn-danger">
                        <x-icon name="x" class="h-4 w-4" /> Tolak
                    </button>
                </form>
            </div>
        @endcan
    @endif
</x-layouts.app>
