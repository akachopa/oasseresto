<x-layouts.app :title="$taxCode->exists ? 'Ubah Kode Pajak' : 'Tambah Kode Pajak'">
    <x-page-header :title="$taxCode->exists ? 'Ubah Kode Pajak' : 'Tambah Kode Pajak'"
                   :back="route('settings.tax-codes.index')" />

    <form method="POST"
          action="{{ $taxCode->exists ? route('settings.tax-codes.update', $taxCode) : route('settings.tax-codes.store') }}"
          class="card card-pad max-w-2xl space-y-4">
        @csrf
        @if ($taxCode->exists)
            @method('PUT')
        @endif

        <div class="form-grid">
            <div>
                <label class="label" for="code">Kode</label>
                <input id="code" name="code" class="input" value="{{ old('code', $taxCode->code) }}" required maxlength="20">
                @error('code') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="name">Nama</label>
                <input id="name" name="name" class="input" value="{{ old('name', $taxCode->name) }}" required>
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="rate">Tarif (%)</label>
                <input id="rate" name="rate" type="number" step="0.0001" class="input"
                       value="{{ old('rate', $taxCode->rate) }}" required>
                @error('rate') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_inclusive" value="1" class="rounded text-brand-500 focus:ring-brand-500"
                       @checked(old('is_inclusive', $taxCode->is_inclusive))>
                Pajak sudah termasuk dalam harga
            </label>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="for_sales" value="1" class="rounded text-brand-500 focus:ring-brand-500"
                       @checked(old('for_sales', $taxCode->for_sales ?? true))>
                Dipakai untuk penjualan
            </label>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="for_purchase" value="1" class="rounded text-brand-500 focus:ring-brand-500"
                       @checked(old('for_purchase', $taxCode->for_purchase ?? true))>
                Dipakai untuk pembelian
            </label>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" class="rounded text-brand-500 focus:ring-brand-500"
                       @checked(old('is_active', $taxCode->is_active ?? true))>
                Aktif
            </label>
        </div>

        <div class="border-hairline flex items-center gap-2 border-t pt-4">
            <button type="submit" class="btn-primary">Simpan</button>
            <a href="{{ route('settings.tax-codes.index') }}" wire:navigate class="btn-secondary">Batal</a>
        </div>
    </form>
</x-layouts.app>
