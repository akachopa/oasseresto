@php
    $mode = old('mode', $record->mode ?? 'discount_percent');
@endphp

<x-layouts.app :title="$record->exists ? 'Ubah Aturan Harga' : 'Tambah Aturan Harga'">
    <x-page-header :title="$record->exists ? 'Ubah Aturan Harga' : 'Tambah Aturan Harga'"
                   subtitle="Kosongkan penentu yang tidak dipakai; kolom kosong berarti berlaku untuk semua."
                   :back="route('price-rules.index')" />

    <form method="POST"
          action="{{ $record->exists ? route('price-rules.update', $record) : route('price-rules.store') }}"
          class="max-w-4xl space-y-4"
          x-data="{ mode: '{{ $mode }}' }">
        @csrf
        @if ($record->exists) @method('PUT') @endif

        <div class="card card-pad space-y-4">
            <p class="section-title">Identitas Aturan</p>

            <div class="form-grid">
                <div class="sm:col-span-2">
                    <label class="label" for="name">Nama Aturan</label>
                    <input id="name" name="name" class="input" value="{{ old('name', $record->name) }}" required
                           placeholder="Contoh: Diskon 5% Dus untuk Grup Toko">
                    @error('name') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="type">Jenis</label>
                    <select id="type" name="type" class="input" required>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}"
                                @selected(old('type', $record->type?->value) === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('type') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="priority">Prioritas</label>
                    <input id="priority" name="priority" type="number" class="input" min="0" max="999"
                           value="{{ old('priority', $record->priority ?? 0) }}" required>
                    <p class="text-muted mt-1 text-xs">Angka lebih besar menang lebih dulu.</p>
                    @error('priority') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="card card-pad space-y-4">
            <p class="section-title">Penentu</p>

            <div class="form-grid">
                <div>
                    <label class="label" for="product_id">Produk</label>
                    <select id="product_id" name="product_id" class="input">
                        <option value="">Semua produk</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}"
                                @selected((int) old('product_id', $record->product_id) === $product->id)>{{ $product->label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label" for="product_category_id">Kategori Produk</label>
                    <select id="product_category_id" name="product_category_id" class="input">
                        <option value="">Semua kategori</option>
                        @foreach ($categories as $id => $name)
                            <option value="{{ $id }}"
                                @selected((int) old('product_category_id', $record->product_category_id) === $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label" for="price_level_id">Level Harga</label>
                    <select id="price_level_id" name="price_level_id" class="input">
                        <option value="">Semua level</option>
                        @foreach ($priceLevels as $id => $name)
                            <option value="{{ $id }}"
                                @selected((int) old('price_level_id', $record->price_level_id) === $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label" for="customer_group_id">Grup Customer</label>
                    <select id="customer_group_id" name="customer_group_id" class="input">
                        <option value="">Semua grup</option>
                        @foreach ($customerGroups as $id => $name)
                            <option value="{{ $id }}"
                                @selected((int) old('customer_group_id', $record->customer_group_id) === $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label" for="customer_id">Customer Tertentu</label>
                    <select id="customer_id" name="customer_id" class="input">
                        <option value="">Semua customer</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}"
                                @selected((int) old('customer_id', $record->customer_id) === $customer->id)>{{ $customer->label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label" for="branch_id">Cabang</label>
                    <select id="branch_id" name="branch_id" class="input">
                        <option value="">Semua cabang</option>
                        @foreach ($branches as $id => $name)
                            <option value="{{ $id }}"
                                @selected((int) old('branch_id', $record->branch_id) === $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label" for="unit_id">Satuan</label>
                    <select id="unit_id" name="unit_id" class="input">
                        <option value="">Semua satuan</option>
                        @foreach ($units as $id => $code)
                            <option value="{{ $id }}"
                                @selected((int) old('unit_id', $record->unit_id) === $id)>{{ $code }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label" for="payment_term">Termin Pembayaran</label>
                    <select id="payment_term" name="payment_term" class="input">
                        <option value="">Semua termin</option>
                        @foreach ($paymentTerms as $value => $label)
                            <option value="{{ $value }}"
                                @selected(old('payment_term', $record->payment_term) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label" for="min_quantity">Kuantitas Minimum (satuan dasar)</label>
                    <input id="min_quantity" name="min_quantity" type="number" step="0.0001" class="input" required
                           value="{{ old('min_quantity', $record->min_quantity ?? 0) }}">
                    @error('min_quantity') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="max_quantity">Kuantitas Maksimum</label>
                    <input id="max_quantity" name="max_quantity" type="number" step="0.0001" class="input"
                           value="{{ old('max_quantity', $record->max_quantity) }}" placeholder="Tanpa batas">
                    @error('max_quantity') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="card card-pad space-y-4">
            <p class="section-title">Nilai & Masa Berlaku</p>

            <div class="form-grid">
                <div>
                    <label class="label" for="mode">Mode</label>
                    <select id="mode" name="mode" class="input" x-model="mode" required>
                        <option value="fixed">Harga Tetap</option>
                        <option value="discount_percent">Diskon Persen</option>
                        <option value="discount_amount">Diskon Nominal</option>
                    </select>
                </div>

                <div x-show="mode === 'fixed'" x-cloak>
                    <label class="label" for="price">Harga Tetap</label>
                    <input id="price" name="price" type="number" step="0.01" class="input"
                           value="{{ old('price', $record->price) }}">
                    @error('price') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div x-show="mode === 'discount_percent'" x-cloak>
                    <label class="label" for="discount_percent">Diskon (%)</label>
                    <input id="discount_percent" name="discount_percent" type="number" step="0.01" class="input"
                           value="{{ old('discount_percent', $record->discount_percent) }}">
                    @error('discount_percent') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div x-show="mode === 'discount_amount'" x-cloak>
                    <label class="label" for="discount_amount">Diskon (Rp)</label>
                    <input id="discount_amount" name="discount_amount" type="number" step="0.01" class="input"
                           value="{{ old('discount_amount', $record->discount_amount) }}">
                    @error('discount_amount') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="valid_from">Berlaku Dari</label>
                    <input id="valid_from" name="valid_from" type="date" class="input"
                           value="{{ old('valid_from', $record->valid_from?->toDateString()) }}">
                    @error('valid_from') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="valid_to">Berlaku Sampai</label>
                    <input id="valid_to" name="valid_to" type="date" class="input"
                           value="{{ old('valid_to', $record->valid_to?->toDateString()) }}">
                    @error('valid_to') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" class="rounded text-brand-500 focus:ring-brand-500"
                       @checked(old('is_active', $record->is_active ?? true))>
                Aktif
            </label>
        </div>

        <div class="flex items-center gap-2">
            <button type="submit" class="btn-primary">Simpan Aturan</button>
            <a href="{{ route('price-rules.index') }}" wire:navigate class="btn-secondary">Batal</a>
        </div>
    </form>
</x-layouts.app>
