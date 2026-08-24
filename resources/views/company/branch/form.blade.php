<x-layouts.app :title="$branch->exists ? 'Ubah Cabang' : 'Tambah Cabang'">
    <x-page-header :title="$branch->exists ? 'Ubah Cabang' : 'Tambah Cabang'"
                   :back="route('settings.branches.index')" />

    <form method="POST"
          action="{{ $branch->exists ? route('settings.branches.update', $branch) : route('settings.branches.store') }}"
          class="card card-pad max-w-3xl space-y-4">
        @csrf
        @if ($branch->exists)
            @method('PUT')
        @endif

        <div class="form-grid">
            <div>
                <label class="label" for="code">Kode Cabang</label>
                <input id="code" name="code" class="input" value="{{ old('code', $branch->code) }}" required maxlength="20">
                @error('code') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="name">Nama Cabang</label>
                <input id="name" name="name" class="input" value="{{ old('name', $branch->name) }}" required>
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="type">Tipe</label>
                <select id="type" name="type" class="input">
                    @foreach (['branch' => 'Cabang', 'outlet' => 'Outlet', 'head_office' => 'Kantor Pusat'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $branch->type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="business_unit_id">Business Unit</label>
                <select id="business_unit_id" name="business_unit_id" class="input">
                    <option value="">-</option>
                    @foreach ($businessUnits as $id => $name)
                        <option value="{{ $id }}" @selected((int) old('business_unit_id', $branch->business_unit_id) === $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="city">Kota</label>
                <input id="city" name="city" class="input" value="{{ old('city', $branch->city) }}">
            </div>

            <div>
                <label class="label" for="phone">Telepon</label>
                <input id="phone" name="phone" class="input" value="{{ old('phone', $branch->phone) }}">
            </div>
        </div>

        <div>
            <label class="label" for="address">Alamat</label>
            <textarea id="address" name="address" rows="2" class="input">{{ old('address', $branch->address) }}</textarea>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" class="rounded text-brand-500 focus:ring-brand-500"
                   @checked(old('is_active', $branch->is_active ?? true))>
            Cabang aktif
        </label>

        <div class="border-hairline flex items-center gap-2 border-t pt-4">
            <button type="submit" class="btn-primary">Simpan</button>
            <a href="{{ route('settings.branches.index') }}" wire:navigate class="btn-secondary">Batal</a>
        </div>
    </form>
</x-layouts.app>
