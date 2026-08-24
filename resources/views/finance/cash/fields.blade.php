<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div>
        <label class="label" for="code">Kode</label>
        <input id="code" name="code" class="input" required value="{{ old('code', $account?->code) }}">
        @error('code') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="label" for="name">Nama Akun</label>
        <input id="name" name="name" class="input" required value="{{ old('name', $account?->name) }}">
        @error('name') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="label" for="type">Jenis</label>
        <select id="type" name="type" class="input">
            @foreach ($types as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $account?->type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('type') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="label" for="branch_id">Cabang</label>
        <select id="branch_id" name="branch_id" class="input">
            <option value="">Semua cabang</option>
            @foreach ($branches as $id => $name)
                <option value="{{ $id }}" @selected((int) old('branch_id', $account?->branch_id) === (int) $id)>{{ $name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="label" for="account_id">Akun COA</label>
        <select id="account_id" name="account_id" class="input">
            <option value="">Belum dipetakan</option>
            @foreach ($accounts as $id => $label)
                <option value="{{ $id }}" @selected((int) old('account_id', $account?->account_id) === (int) $id)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="label" for="opening_balance">Saldo Awal</label>
        <input id="opening_balance" name="opening_balance" type="number" step="0.01" min="0" class="input text-right"
               value="{{ old('opening_balance', $account?->opening_balance ?? 0) }}"
               @disabled($account !== null)>
        @if ($account)
            <p class="text-muted mt-1 text-xs">Saldo awal tidak bisa diubah karena buku kas sudah berjalan.</p>
        @endif
    </div>

    <div>
        <label class="label" for="bank_name">Nama Bank</label>
        <input id="bank_name" name="bank_name" class="input" value="{{ old('bank_name', $account?->bank_name) }}">
    </div>

    <div>
        <label class="label" for="account_number">Nomor Rekening</label>
        <input id="account_number" name="account_number" class="input"
               value="{{ old('account_number', $account?->account_number) }}">
    </div>

    <div>
        <label class="label" for="account_holder">Nama Pemilik</label>
        <input id="account_holder" name="account_holder" class="input"
               value="{{ old('account_holder', $account?->account_holder) }}">
    </div>

    <div class="sm:col-span-3">
        <label class="label" for="note">Catatan</label>
        <input id="note" name="note" class="input" value="{{ old('note', $account?->note) }}">
    </div>
</div>

<div class="flex flex-wrap items-center gap-4">
    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="is_default" value="0">
        <input type="checkbox" name="is_default" value="1" class="rounded"
               @checked(old('is_default', $account?->is_default)) >
        Jadikan akun default
    </label>

    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="rounded"
               @checked(old('is_active', $account?->is_active ?? true)) >
        Aktif
    </label>
</div>
