<x-layouts.app :title="$record->exists ? 'Ubah Grup Customer' : 'Tambah Grup Customer'">
    <x-page-header :title="$record->exists ? 'Ubah Grup Customer' : 'Tambah Grup Customer'"
                   :back="route('customer-groups.index')" />

    <form method="POST"
          action="{{ $record->exists ? route('customer-groups.update', $record) : route('customer-groups.store') }}"
          class="card card-pad max-w-2xl space-y-4">
        @csrf
        @if ($record->exists) @method('PUT') @endif

        <div class="form-grid">
            <div>
                <label class="label" for="code">Kode</label>
                <input id="code" name="code" class="input" value="{{ old('code', $record->code) }}" required maxlength="30">
                @error('code') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="name">Nama Grup</label>
                <input id="name" name="name" class="input" value="{{ old('name', $record->name) }}" required>
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="price_level_id">Level Harga Default</label>
                <select id="price_level_id" name="price_level_id" class="input">
                    <option value="">Ikut default company</option>
                    @foreach ($priceLevels as $id => $name)
                        <option value="{{ $id }}"
                            @selected((int) old('price_level_id', $record->price_level_id) === $id)>{{ $name }}</option>
                    @endforeach
                </select>
                @error('price_level_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="default_payment_term">Termin Default</label>
                <select id="default_payment_term" name="default_payment_term" class="input" required>
                    @foreach ($paymentTerms as $value => $label)
                        <option value="{{ $value }}"
                            @selected(old('default_payment_term', $record->default_payment_term?->value ?? 'cash') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('default_payment_term') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="default_credit_limit">Credit Limit Default</label>
                <input id="default_credit_limit" name="default_credit_limit" type="number" step="0.01" class="input"
                       value="{{ old('default_credit_limit', $record->default_credit_limit ?? 0) }}" required min="0">
                @error('default_credit_limit') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" class="rounded text-brand-500 focus:ring-brand-500"
                   @checked(old('is_active', $record->is_active ?? true))>
            Aktif
        </label>

        <div class="border-hairline flex items-center gap-2 border-t pt-4">
            <button type="submit" class="btn-primary">Simpan</button>
            <a href="{{ route('customer-groups.index') }}" wire:navigate class="btn-secondary">Batal</a>
        </div>
    </form>
</x-layouts.app>
