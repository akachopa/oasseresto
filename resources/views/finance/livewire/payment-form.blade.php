@php
    use App\Modules\Core\Support\Money;
@endphp

<div class="space-y-4">
    <div class="card card-pad space-y-4">
        <p class="section-title">Informasi Pembayaran</p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="label" for="supplier_id">Supplier</label>
                <select id="supplier_id" wire:model.live="form.supplier_id" class="input" required>
                    <option value="">Pilih supplier</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->label }}</option>
                    @endforeach
                </select>
                @error('form.supplier_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="cash_account_id">Dibayar dari</label>
                <select id="cash_account_id" wire:model.live="form.cash_account_id" class="input" required>
                    <option value="">Pilih akun</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->label }}</option>
                    @endforeach
                </select>
                <p class="text-muted mt-1 text-xs">Saldo tersedia {{ Money::rupiah($availableBalance) }}</p>
                @error('form.cash_account_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="method">Metode</label>
                <select id="method" wire:model="form.method" class="input">
                    @foreach ($methods as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('form.method') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="payment_date">Tanggal</label>
                <input id="payment_date" type="date" wire:model="form.payment_date" class="input" required>
                @error('form.payment_date') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="amount">Nilai Dibayar</label>
                <input id="amount" type="number" step="0.01" min="0"
                       wire:model.live.debounce.500ms="form.amount" class="input text-right" required>
                @error('form.amount') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="reference">Referensi</label>
                <input id="reference" wire:model="form.reference" class="input"
                       placeholder="Nomor transfer atau nomor cek">
            </div>

            <div class="sm:col-span-3">
                <label class="label" for="note">Catatan</label>
                <input id="note" wire:model="form.note" class="input">
            </div>
        </div>
    </div>

    <div class="card card-pad space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="section-title">Alokasi ke Hutang</p>

            <button type="button" wire:click="autoAllocate" class="btn-secondary">
                <x-icon name="sparkles" class="h-4 w-4" /> Alokasi Otomatis
            </button>
        </div>

        @error('lines') <p class="field-error">{{ $message }}</p> @enderror

        @if ($lines === [])
            <x-empty-state icon="wallet" title="Belum ada hutang terbuka"
                           message="Pilih supplier yang punya hutang berjalan untuk mengalokasikan pembayaran." />
        @else
            <div class="oasse-table-wrap">
                <table class="table-oasse">
                    <thead>
                        <tr>
                            <th>Dokumen</th>
                            <th>Jatuh Tempo</th>
                            <th class="text-right">Sisa Hutang</th>
                            <th class="text-right">Dibayar</th>
                            <th class="text-right">Diskon</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $index => $line)
                            <tr wire:key="pay-alloc-{{ $line['target_id'] }}">
                                <td class="font-mono text-xs">{{ $line['document_number'] }}</td>
                                <td>
                                    {{ $line['due_date'] }}
                                    @if ($line['days_overdue'] > 0)
                                        <span class="badge-danger ml-1">{{ $line['days_overdue'] }} hari</span>
                                    @endif
                                </td>
                                <td class="text-right">{{ Money::rupiah($line['outstanding']) }}</td>
                                <td class="text-right">
                                    <input type="number" step="0.01" min="0" max="{{ $line['outstanding'] }}"
                                           wire:model.live.debounce.500ms="lines.{{ $index }}.amount"
                                           class="input w-32 text-right">
                                </td>
                                <td class="text-right">
                                    <input type="number" step="0.01" min="0"
                                           wire:model.live.debounce.500ms="lines.{{ $index }}.discount_amount"
                                           class="input w-28 text-right">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <dl class="border-hairline ml-auto w-full max-w-xs space-y-1 border-t pt-3 text-sm sm:w-72">
                <div class="flex items-center justify-between">
                    <dt class="text-muted">Nilai dibayar</dt>
                    <dd>{{ Money::rupiah($form['amount'] ?? 0) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-muted">Dialokasikan</dt>
                    <dd>{{ Money::rupiah($allocatedTotal) }}</dd>
                </div>
                <div class="border-hairline flex items-center justify-between border-t pt-1 font-semibold">
                    <dt>Belum dialokasikan</dt>
                    <dd class="{{ $unallocated < 0 ? 'text-negative' : '' }}">{{ Money::rupiah($unallocated) }}</dd>
                </div>
            </dl>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <button type="button" wire:click="save(false)" wire:loading.attr="disabled" class="btn-secondary">
            Simpan Draft
        </button>
        <button type="button" wire:click="save(true)" wire:loading.attr="disabled" class="btn-primary">
            <span wire:loading.remove wire:target="save">Simpan & Posting</span>
            <span wire:loading wire:target="save">Memproses...</span>
        </button>
        <a href="{{ route('finance.payments.index') }}" wire:navigate class="btn-ghost">Batal</a>
    </div>
</div>
