<x-layouts.app :title="$rule->exists ? 'Ubah Aturan Approval' : 'Tambah Aturan Approval'">
    <x-page-header :title="$rule->exists ? 'Ubah Aturan Approval' : 'Tambah Aturan Approval'"
                   :back="route('approval.rules.index')" />

    <form method="POST"
          action="{{ $rule->exists ? route('approval.rules.update', $rule) : route('approval.rules.store') }}"
          class="card card-pad max-w-2xl space-y-4">
        @csrf
        @if ($rule->exists) @method('PUT') @endif

        <div class="form-grid">
            <div>
                <label class="label" for="document_type">Jenis dokumen</label>
                <select id="document_type" name="document_type" class="input" required>
                    @foreach ($documentTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('document_type', $rule->document_type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="name">Nama aturan</label>
                <input id="name" name="name" class="input" value="{{ old('name', $rule->name) }}" required>
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="sequence">Urutan</label>
                <input id="sequence" name="sequence" type="number" min="1" class="input"
                       value="{{ old('sequence', $rule->sequence) }}" required>
            </div>

            <div>
                <label class="label" for="trigger">Pemicu</label>
                <select id="trigger" name="trigger" class="input">
                    @foreach ($triggers as $value => $label)
                        <option value="{{ $value }}" @selected(old('trigger', $rule->trigger) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="min_amount">Nilai minimum</label>
                <input id="min_amount" name="min_amount" type="number" step="0.01" class="input"
                       value="{{ old('min_amount', $rule->min_amount) }}" required>
            </div>

            <div>
                <label class="label" for="max_amount">Nilai maksimum</label>
                <input id="max_amount" name="max_amount" type="number" step="0.01" class="input"
                       value="{{ old('max_amount', $rule->max_amount) }}" placeholder="Kosong = tanpa batas">
            </div>

            <div>
                <label class="label" for="approver_role">Peran penyetuju</label>
                <select id="approver_role" name="approver_role" class="input">
                    <option value="">-</option>
                    @foreach ($roles as $name)
                        <option value="{{ $name }}" @selected(old('approver_role', $rule->approver_role) === $name)>{{ $name }}</option>
                    @endforeach
                </select>
                @error('approver_role') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="approver_user_id">Atau user tertentu</label>
                <select id="approver_user_id" name="approver_user_id" class="input">
                    <option value="">-</option>
                    @foreach ($users as $id => $name)
                        <option value="{{ $id }}" @selected((string) old('approver_user_id', $rule->approver_user_id) === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="branch_id">Cabang (opsional)</label>
                <select id="branch_id" name="branch_id" class="input">
                    <option value="">Semua cabang</option>
                    @foreach ($branches as $id => $name)
                        <option value="{{ $id }}" @selected((string) old('branch_id', $rule->branch_id) === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="label" for="note">Catatan</label>
            <textarea id="note" name="note" class="input" rows="2">{{ old('note', $rule->note) }}</textarea>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" class="rounded text-brand-500"
                   @checked(old('is_active', $rule->is_active ?? true))>
            Aktif
        </label>

        <div class="border-hairline flex items-center gap-2 border-t pt-4">
            <button type="submit" class="btn-primary">Simpan</button>
            <a href="{{ route('approval.rules.index') }}" wire:navigate class="btn-secondary">Batal</a>
        </div>
    </form>
</x-layouts.app>
