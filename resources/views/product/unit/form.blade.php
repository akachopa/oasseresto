<x-layouts.app :title="$record->exists ? 'Ubah Satuan' : 'Tambah Satuan'">
    <x-page-header :title="$record->exists ? 'Ubah Satuan' : 'Tambah Satuan'" :back="route('units.index')" />

    <form method="POST"
          action="{{ $record->exists ? route('units.update', $record) : route('units.store') }}"
          class="card card-pad max-w-xl space-y-4">
        @csrf
        @if ($record->exists) @method('PUT') @endif

        <div class="form-grid">
            <div>
                <label class="label" for="code">Kode</label>
                <input id="code" name="code" class="input" value="{{ old('code', $record->code) }}" required maxlength="20">
                @error('code') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="name">Nama</label>
                <input id="name" name="name" class="input" value="{{ old('name', $record->name) }}" required>
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="category">Jenis</label>
                <select id="category" name="category" class="input">
                    @foreach (['count' => 'Hitungan', 'weight' => 'Berat', 'volume' => 'Volume', 'length' => 'Panjang'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('category', $record->category) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" class="rounded text-brand-500 focus:ring-brand-500"
                   @checked(old('is_active', $record->is_active ?? true))>
            Aktif
        </label>

        <div class="border-hairline flex items-center gap-2 border-t pt-4">
            <button type="submit" class="btn-primary">Simpan</button>
            <a href="{{ route('units.index') }}" wire:navigate class="btn-secondary">Batal</a>
        </div>
    </form>
</x-layouts.app>
