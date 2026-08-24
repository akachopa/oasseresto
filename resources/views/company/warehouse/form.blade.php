<x-layouts.app :title="$warehouse->exists ? 'Ubah Gudang' : 'Tambah Gudang'">
    <x-page-header :title="$warehouse->exists ? 'Ubah Gudang' : 'Tambah Gudang'"
                   :back="route('settings.warehouses.index')" />

    <form method="POST"
          action="{{ $warehouse->exists ? route('settings.warehouses.update', $warehouse) : route('settings.warehouses.store') }}"
          class="card card-pad max-w-3xl space-y-4">
        @csrf
        @if ($warehouse->exists)
            @method('PUT')
        @endif

        <div class="form-grid">
            <div>
                <label class="label" for="code">Kode Gudang</label>
                <input id="code" name="code" class="input" value="{{ old('code', $warehouse->code) }}" required maxlength="20">
                @error('code') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="name">Nama Gudang</label>
                <input id="name" name="name" class="input" value="{{ old('name', $warehouse->name) }}" required>
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="branch_id">Cabang</label>
                <select id="branch_id" name="branch_id" class="input" required>
                    @foreach ($branches as $id => $name)
                        <option value="{{ $id }}" @selected((int) old('branch_id', $warehouse->branch_id) === $id)>{{ $name }}</option>
                    @endforeach
                </select>
                @error('branch_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="type">Tipe</label>
                <select id="type" name="type" class="input">
                    @foreach (['main' => 'Gudang Utama', 'outlet' => 'Gudang Outlet', 'transit' => 'Transit', 'damaged' => 'Barang Rusak'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $warehouse->type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="label" for="address">Alamat</label>
            <textarea id="address" name="address" rows="2" class="input">{{ old('address', $warehouse->address) }}</textarea>
        </div>

        <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_sellable" value="1" class="rounded text-brand-500 focus:ring-brand-500"
                       @checked(old('is_sellable', $warehouse->is_sellable ?? true))>
                Stok di gudang ini boleh dijual
            </label>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" class="rounded text-brand-500 focus:ring-brand-500"
                       @checked(old('is_active', $warehouse->is_active ?? true))>
                Gudang aktif
            </label>
        </div>

        <div class="border-hairline flex items-center gap-2 border-t pt-4">
            <button type="submit" class="btn-primary">Simpan</button>
            <a href="{{ route('settings.warehouses.index') }}" wire:navigate class="btn-secondary">Batal</a>
        </div>
    </form>
</x-layouts.app>
