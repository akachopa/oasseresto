<x-layouts.app title="Buat Stock Opname">
    <x-page-header title="Buat Stock Opname"
                   subtitle="Lembar hitung dibuat dari saldo saat ini; produk berbatch dipecah per batch."
                   :back="route('inventory.opnames.index')" />

    <form method="POST" action="{{ route('inventory.opnames.store') }}" class="space-y-4"
          x-data="{ scope: '{{ old('scope_type', 'full') }}' }">
        @csrf

        <div class="card card-pad space-y-4">
            <p class="section-title">Cakupan Hitung</p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="warehouse_id">Gudang</label>
                    <select id="warehouse_id" name="warehouse_id" class="input" required>
                        <option value="">Pilih gudang</option>
                        @foreach ($warehouses as $id => $name)
                            <option value="{{ $id }}" @selected(old('warehouse_id') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('warehouse_id') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="opname_date">Tanggal</label>
                    <input id="opname_date" type="date" name="opname_date" class="input" required
                           value="{{ old('opname_date', now()->toDateString()) }}">
                    @error('opname_date') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="scope_type">Jenis Opname</label>
                    <select id="scope_type" name="scope_type" class="input" x-model="scope" required>
                        <option value="full">Seluruh Gudang</option>
                        <option value="category">Per Kategori</option>
                    </select>
                    @error('scope_type') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div x-show="scope === 'category'" x-cloak>
                    <label class="label" for="product_category_id">Kategori</label>
                    <select id="product_category_id" name="product_category_id" class="input">
                        <option value="">Pilih kategori</option>
                        @foreach ($categories as $id => $name)
                            <option value="{{ $id }}" @selected(old('product_category_id') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('product_category_id') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="label" for="note">Catatan</label>
                <input id="note" name="note" class="input" value="{{ old('note') }}"
                       placeholder="Misal: opname bulanan gudang utama">
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="btn-primary">
                <x-icon name="clipboard" class="h-4 w-4" /> Buat Lembar Hitung
            </button>
            <a href="{{ route('inventory.opnames.index') }}" wire:navigate class="btn-ghost">Batal</a>
        </div>
    </form>
</x-layouts.app>
