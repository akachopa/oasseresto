<x-layouts.app :title="$record->exists ? 'Ubah Level Harga' : 'Tambah Level Harga'">
    <x-page-header :title="$record->exists ? 'Ubah Level Harga' : 'Tambah Level Harga'" :back="route('price-levels.index')" />

    <form method="POST"
          action="{{ $record->exists ? route('price-levels.update', $record) : route('price-levels.store') }}"
          class="card card-pad max-w-xl space-y-4">
        @csrf
        @if ($record->exists) @method('PUT') @endif

        <div class="form-grid">
            <div>
                <label class="label" for="code">Kode</label>
                <input id="code" name="code" class="input" value="{{ old('code', $record->code) }}" required maxlength="30">
                @error('code') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="name">Nama Level</label>
                <input id="name" name="name" class="input" value="{{ old('name', $record->name) }}" required>
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="sequence">Urutan</label>
                <input id="sequence" name="sequence" type="number" class="input"
                       value="{{ old('sequence', $record->sequence ?? 0) }}" required min="0">
                @error('sequence') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_default" value="1" class="rounded text-brand-500 focus:ring-brand-500"
                       @checked(old('is_default', $record->is_default ?? false))>
                Jadikan level default untuk customer tanpa level harga
            </label>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" class="rounded text-brand-500 focus:ring-brand-500"
                       @checked(old('is_active', $record->is_active ?? true))>
                Aktif
            </label>
        </div>

        <div class="border-hairline flex items-center gap-2 border-t pt-4">
            <button type="submit" class="btn-primary">Simpan</button>
            <a href="{{ route('price-levels.index') }}" wire:navigate class="btn-secondary">Batal</a>
        </div>
    </form>
</x-layouts.app>
