<x-layouts.app :title="$record->exists ? 'Ubah Kategori' : 'Tambah Kategori'">
    <x-page-header :title="$record->exists ? 'Ubah Kategori' : 'Tambah Kategori'" :back="route('product-categories.index')" />

    <form method="POST"
          action="{{ $record->exists ? route('product-categories.update', $record) : route('product-categories.store') }}"
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
                <label class="label" for="name">Nama Kategori</label>
                <input id="name" name="name" class="input" value="{{ old('name', $record->name) }}" required>
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="parent_id">Kategori Induk</label>
                <select id="parent_id" name="parent_id" class="input">
                    <option value="">-</option>
                    @foreach ($parents as $id => $name)
                        @continue($record->exists && $record->id === $id)
                        <option value="{{ $id }}" @selected((int) old('parent_id', $record->parent_id) === $id)>{{ $name }}</option>
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
            <a href="{{ route('product-categories.index') }}" wire:navigate class="btn-secondary">Batal</a>
        </div>
    </form>
</x-layouts.app>
