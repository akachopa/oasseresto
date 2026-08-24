<x-layouts.app title="Jurnal Manual">
    <x-page-header title="Jurnal Manual" :back="route('accounting.journals.index')"
                   subtitle="Debit dan kredit harus seimbang. Jurnal langsung berstatus diposting." />

    <form method="POST" action="{{ route('accounting.journals.store') }}" class="card card-pad space-y-4"
          x-data="journalForm()">
        @csrf

        <div class="form-grid">
            <div>
                <label class="label" for="entry_date">Tanggal</label>
                <input id="entry_date" name="entry_date" type="date" class="input"
                       value="{{ old('entry_date', now()->toDateString()) }}" required>
            </div>
            <div class="sm:col-span-2">
                <label class="label" for="description">Keterangan</label>
                <input id="description" name="description" class="input" value="{{ old('description') }}" required>
            </div>
        </div>

        <div class="oasse-table-wrap">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>Akun</th>
                        <th class="text-right">Debit</th>
                        <th class="text-right">Kredit</th>
                        <th>Memo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(line, index) in lines" :key="index">
                        <tr>
                            <td>
                                <select class="input" :name="`lines[${index}][account_id]`" x-model="line.account_id" required>
                                    <option value="">Pilih akun</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" class="input text-right"
                                       :name="`lines[${index}][debit]`" x-model.number="line.debit">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" class="input text-right"
                                       :name="`lines[${index}][credit]`" x-model.number="line.credit">
                            </td>
                            <td>
                                <input class="input" :name="`lines[${index}][memo]`" x-model="line.memo">
                            </td>
                            <td>
                                <button type="button" class="btn-icon text-muted" @click="remove(index)" x-show="lines.length > 2">
                                    <x-icon name="trash" class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between gap-3">
            <button type="button" class="btn-secondary" @click="add()">Tambah baris</button>
            <p class="text-sm" :class="balanced ? 'text-positive' : 'text-negative'">
                Debit <span x-text="format(totalDebit)"></span> · Kredit <span x-text="format(totalCredit)"></span>
            </p>
        </div>

        <div>
            <label class="label" for="note">Catatan</label>
            <textarea id="note" name="note" class="input" rows="2">{{ old('note') }}</textarea>
        </div>

        <div class="border-hairline flex items-center gap-2 border-t pt-4">
            <button type="submit" class="btn-primary" :disabled="!balanced">Posting</button>
            <a href="{{ route('accounting.journals.index') }}" class="btn-secondary">Batal</a>
        </div>
    </form>

    <script>
        function journalForm() {
            return {
                lines: [
                    { account_id: '', debit: 0, credit: 0, memo: '' },
                    { account_id: '', debit: 0, credit: 0, memo: '' },
                ],
                add() { this.lines.push({ account_id: '', debit: 0, credit: 0, memo: '' }); },
                remove(index) { this.lines.splice(index, 1); },
                get totalDebit() { return this.lines.reduce((s, l) => s + (Number(l.debit) || 0), 0); },
                get totalCredit() { return this.lines.reduce((s, l) => s + (Number(l.credit) || 0), 0); },
                get balanced() { return Math.abs(this.totalDebit - this.totalCredit) < 0.0001 && this.totalDebit > 0; },
                format(n) { return new Intl.NumberFormat('id-ID').format(n); },
            };
        }
    </script>
</x-layouts.app>
