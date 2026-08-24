@php
    use App\Modules\Core\Enums\AccountType;
@endphp

<x-layouts.app :title="$record->exists ? 'Ubah Akun' : 'Tambah Akun'">
    <x-page-header :title="$record->exists ? 'Ubah Akun' : 'Tambah Akun'"
                   :back="route('accounting.accounts.index')" />

    <form method="POST"
          action="{{ $record->exists ? route('accounting.accounts.update', $record) : route('accounting.accounts.store') }}"
          class="card card-pad max-w-2xl space-y-4">
        @csrf
        @if ($record->exists) @method('PUT') @endif

        <div class="form-grid">
            <div>
                <label class="label" for="code">Kode</label>
                <input id="code" name="code" class="input" value="{{ old('code', $record->code) }}" required maxlength="30"
                       @disabled($record->is_system)>
                @error('code') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="name">Nama</label>
                <input id="name" name="name" class="input" value="{{ old('name', $record->name) }}" required>
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="type">Tipe</label>
                <select id="type" name="type" class="input" @disabled($record->is_system)>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $record->type?->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="parent_id">Induk</label>
                <select id="parent_id" name="parent_id" class="input">
                    <option value="">-</option>
                    @foreach ($parents as $id => $label)
                        <option value="{{ $id }}" @selected((string) old('parent_id', $record->parent_id) === (string) $id)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="slug">Slug mapping</label>
                <input id="slug" name="slug" class="input" value="{{ old('slug', $record->slug) }}" maxlength="60"
                       @disabled($record->is_system)>
                <p class="text-muted mt-1 text-xs">Dipakai posting otomatis. Jangan diubah untuk akun sistem.</p>
            </div>
        </div>

        <div>
            <label class="label" for="description">Catatan</label>
            <textarea id="description" name="description" class="input" rows="2">{{ old('description', $record->description) }}</textarea>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_postable" value="1" class="rounded text-brand-500"
                   @checked(old('is_postable', $record->is_postable ?? true))>
            Bisa diposting (bukan header)
        </label>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" class="rounded text-brand-500"
                   @checked(old('is_active', $record->is_active ?? true))>
            Aktif
        </label>

        <div class="border-hairline flex items-center gap-2 border-t pt-4">
            <button type="submit" class="btn-primary">Simpan</button>
            <a href="{{ route('accounting.accounts.index') }}" wire:navigate class="btn-secondary">Batal</a>
        </div>
    </form>
</x-layouts.app>
