<div class="max-w-5xl space-y-4">
    <div class="card card-pad space-y-4">
        <p class="section-title">Identitas Produk</p>

        <div class="form-grid">
            <div>
                <label class="label" for="sku">SKU</label>
                <input id="sku" wire:model="form.sku" class="input" required>
                @error('form.sku') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="barcode">Barcode</label>
                <input id="barcode" wire:model="form.barcode" class="input">
                @error('form.barcode') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="label" for="name">Nama Produk</label>
                <input id="name" wire:model="form.name" class="input" required>
                @error('form.name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="short_name">Nama Singkat (struk)</label>
                <input id="short_name" wire:model="form.short_name" class="input">
            </div>

            <div>
                <label class="label" for="product_category_id">Kategori</label>
                <select id="product_category_id" wire:model="form.product_category_id" class="input">
                    <option value="">-</option>
                    @foreach ($categories as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="brand_id">Brand</label>
                <select id="brand_id" wire:model="form.brand_id" class="input">
                    <option value="">-</option>
                    @foreach ($brands as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="tax_code_id">Kode Pajak</label>
                <select id="tax_code_id" wire:model="form.tax_code_id" class="input">
                    <option value="">-</option>
                    @foreach ($taxCodes as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="card card-pad space-y-4">
        <p class="section-title">Satuan & Konversi</p>
        <p class="text-muted text-sm">
            Seluruh stok dan harga pokok dihitung dalam satuan dasar. Satuan turunan hanya menentukan
            faktor konversi, misalnya 1 Dus = 24 Pcs.
        </p>

        <div class="form-grid">
            <div>
                <label class="label" for="base_unit_id">Satuan Dasar</label>
                <select id="base_unit_id" wire:model="form.base_unit_id" class="input" required>
                    @foreach ($unitOptions as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->code }} - {{ $unit->name }}</option>
                    @endforeach
                </select>
                @error('form.base_unit_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="purchase_unit_id">Satuan Pembelian Default</label>
                <select id="purchase_unit_id" wire:model="form.purchase_unit_id" class="input">
                    <option value="">Ikut satuan dasar</option>
                    @foreach ($unitOptions as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->code }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="sales_unit_id">Satuan Penjualan Default</label>
                <select id="sales_unit_id" wire:model="form.sales_unit_id" class="input">
                    <option value="">Ikut satuan dasar</option>
                    @foreach ($unitOptions as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->code }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="space-y-2">
            @foreach ($units as $index => $unitRow)
                <div class="border-hairline grid grid-cols-1 items-end gap-3 rounded-lg border p-3 sm:grid-cols-12"
                     wire:key="unit-{{ $index }}">
                    <div class="sm:col-span-3">
                        <label class="label">Satuan</label>
                        <select wire:model="units.{{ $index }}.unit_id" class="input">
                            <option value="">Pilih satuan</option>
                            @foreach ($unitOptions as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->code }} - {{ $unit->name }}</option>
                            @endforeach
                        </select>
                        @error('units.'.$index.'.unit_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-3">
                        <label class="label">Isi per Satuan Dasar</label>
                        <input type="number" step="0.000001" wire:model="units.{{ $index }}.conversion_to_base" class="input">
                        @error('units.'.$index.'.conversion_to_base') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-3">
                        <label class="label">Barcode Satuan</label>
                        <input wire:model="units.{{ $index }}.barcode" class="input">
                    </div>

                    <div class="flex items-center gap-3 sm:col-span-2">
                        <label class="flex items-center gap-1.5 text-xs">
                            <input type="checkbox" wire:model="units.{{ $index }}.allow_purchase"
                                   class="rounded text-brand-500 focus:ring-brand-500">
                            Beli
                        </label>
                        <label class="flex items-center gap-1.5 text-xs">
                            <input type="checkbox" wire:model="units.{{ $index }}.allow_sales"
                                   class="rounded text-brand-500 focus:ring-brand-500">
                            Jual
                        </label>
                    </div>

                    <div class="sm:col-span-1">
                        <button type="button" wire:click="removeUnitRow({{ $index }})"
                                class="btn-icon text-muted hover:bg-negative/10 hover:text-negative">
                            <x-icon name="trash" class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            @endforeach

            <button type="button" wire:click="addUnitRow" class="btn-secondary">
                <x-icon name="plus" class="h-4 w-4" /> Tambah Satuan Turunan
            </button>
        </div>
    </div>

    <div class="card card-pad space-y-4">
        <p class="section-title">Harga</p>

        <div class="form-grid">
            <div>
                <label class="label" for="base_price">Harga Dasar (per satuan dasar)</label>
                <input id="base_price" type="number" step="0.01" wire:model="form.base_price" class="input">
                @error('form.base_price') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="min_margin_percent">Margin Minimum (%)</label>
                <input id="min_margin_percent" type="number" step="0.01" wire:model="form.min_margin_percent" class="input"
                       placeholder="Ikut pengaturan company">
                @error('form.min_margin_percent') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="space-y-2">
            @foreach ($prices as $index => $priceRow)
                <div class="border-hairline grid grid-cols-1 items-end gap-3 rounded-lg border p-3 sm:grid-cols-12"
                     wire:key="price-{{ $index }}">
                    <div class="sm:col-span-4">
                        <label class="label">Level Harga</label>
                        <select wire:model="prices.{{ $index }}.price_level_id" class="input">
                            @foreach ($priceLevels as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('prices.'.$index.'.price_level_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-3">
                        <label class="label">Satuan</label>
                        <select wire:model="prices.{{ $index }}.unit_id" class="input">
                            @foreach ($unitOptions as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->code }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-4">
                        <label class="label">Harga</label>
                        <input type="number" step="0.01" wire:model="prices.{{ $index }}.price" class="input">
                        @error('prices.'.$index.'.price') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-1">
                        <button type="button" wire:click="removePriceRow({{ $index }})"
                                class="btn-icon text-muted hover:bg-negative/10 hover:text-negative">
                            <x-icon name="trash" class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            @endforeach

            <button type="button" wire:click="addPriceRow" class="btn-secondary">
                <x-icon name="plus" class="h-4 w-4" /> Tambah Harga Level
            </button>
        </div>
    </div>

    <div class="card card-pad space-y-4">
        <p class="section-title">Stok & Pengendalian</p>

        <div class="form-grid">
            <div>
                <label class="label" for="minimum_stock">Stok Minimum</label>
                <input id="minimum_stock" type="number" step="0.0001" wire:model="form.minimum_stock" class="input">
            </div>

            <div>
                <label class="label" for="maximum_stock">Stok Maksimum</label>
                <input id="maximum_stock" type="number" step="0.0001" wire:model="form.maximum_stock" class="input">
            </div>

            <div>
                <label class="label" for="reorder_point">Titik Reorder</label>
                <input id="reorder_point" type="number" step="0.0001" wire:model="form.reorder_point" class="input">
            </div>

            <div>
                <label class="label" for="safety_stock">Safety Stock</label>
                <input id="safety_stock" type="number" step="0.0001" wire:model="form.safety_stock" class="input">
            </div>

            <div>
                <label class="label" for="lead_time_days">Lead Time (hari)</label>
                <input id="lead_time_days" type="number" wire:model="form.lead_time_days" class="input">
            </div>
        </div>

        <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="form.is_stocked" class="rounded text-brand-500 focus:ring-brand-500">
                Produk disimpan sebagai stok
            </label>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="form.track_batch" class="rounded text-brand-500 focus:ring-brand-500">
                Lacak nomor batch
            </label>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="form.track_expiry" class="rounded text-brand-500 focus:ring-brand-500">
                Lacak tanggal kedaluwarsa (otomatis mengaktifkan batch)
            </label>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="form.is_active" class="rounded text-brand-500 focus:ring-brand-500">
                Produk aktif
            </label>
        </div>
    </div>

    <div class="card card-pad space-y-4">
        <p class="section-title">Akun Akuntansi</p>
        <p class="text-muted text-sm">Kosongkan untuk memakai akun default company.</p>

        <div class="form-grid">
            @foreach ([
                'inventory_account_id' => 'Akun Persediaan',
                'cogs_account_id' => 'Akun HPP',
                'revenue_account_id' => 'Akun Pendapatan',
            ] as $field => $label)
                <div>
                    <label class="label" for="{{ $field }}">{{ $label }}</label>
                    <select id="{{ $field }}" wire:model="form.{{ $field }}" class="input">
                        <option value="">Default company</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->label }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        </div>
    </div>

    <div class="flex items-center gap-2">
        <button type="button" wire:click="save" wire:loading.attr="disabled" class="btn-primary">
            <span wire:loading.remove wire:target="save">Simpan Produk</span>
            <span wire:loading wire:target="save">Menyimpan...</span>
        </button>
        <a href="{{ route('products.index') }}" wire:navigate class="btn-secondary">Batal</a>
    </div>
</div>
