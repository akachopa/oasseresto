<div class="max-w-5xl space-y-4">
    <div class="card card-pad space-y-4">
        <p class="section-title">Identitas</p>

        <div class="form-grid">
            <div>
                <label class="label" for="code">Kode Supplier</label>
                <input id="code" wire:model="form.code" class="input" required>
                @error('form.code') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="name">Nama Supplier</label>
                <input id="name" wire:model="form.name" class="input" required>
                @error('form.name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="pic_name">Nama PIC</label>
                <input id="pic_name" wire:model="form.pic_name" class="input">
            </div>

            <div>
                <label class="label" for="phone">Telepon</label>
                <input id="phone" wire:model="form.phone" class="input">
            </div>

            <div>
                <label class="label" for="whatsapp">WhatsApp</label>
                <input id="whatsapp" wire:model="form.whatsapp" class="input">
            </div>

            <div>
                <label class="label" for="email">Email</label>
                <input id="email" type="email" wire:model="form.email" class="input">
                @error('form.email') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="label" for="address">Alamat</label>
                <textarea id="address" wire:model="form.address" rows="2" class="input"></textarea>
            </div>

            <div>
                <label class="label" for="city">Kota</label>
                <input id="city" wire:model="form.city" class="input">
            </div>

            <div>
                <label class="label" for="tax_number">NPWP</label>
                <input id="tax_number" wire:model="form.tax_number" class="input">
            </div>
        </div>
    </div>

    <div class="card card-pad space-y-4">
        <p class="section-title">Termin & Rekening</p>

        <div class="form-grid">
            <div>
                <label class="label" for="payment_term">Termin Pembayaran</label>
                <select id="payment_term" wire:model="form.payment_term" class="input" required>
                    @foreach ($paymentTerms as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('form.payment_term') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="lead_time_days">Lead Time (hari)</label>
                <input id="lead_time_days" type="number" wire:model="form.lead_time_days" class="input" min="0">
                @error('form.lead_time_days') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="minimum_order_amount">Minimum Order</label>
                <input id="minimum_order_amount" type="number" step="0.01" wire:model="form.minimum_order_amount" class="input">
                @error('form.minimum_order_amount') <p class="field-error">{{ $message }}</p> @enderror
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

            <div>
                <label class="label" for="bank_name">Nama Bank</label>
                <input id="bank_name" wire:model="form.bank_name" class="input">
            </div>

            <div>
                <label class="label" for="bank_account">Nomor Rekening</label>
                <input id="bank_account" wire:model="form.bank_account" class="input">
            </div>

            <div>
                <label class="label" for="bank_account_name">Nama Pemilik Rekening</label>
                <input id="bank_account_name" wire:model="form.bank_account_name" class="input">
            </div>
        </div>
    </div>

    <div class="card card-pad space-y-4">
        <div class="flex items-center justify-between gap-2">
            <p class="section-title">Barang yang Dipasok</p>
            <button type="button" wire:click="addItemRow" class="btn-secondary">
                <x-icon name="plus" class="h-4 w-4" /> Tambah Barang
            </button>
        </div>

        @if ($items === [])
            <p class="text-muted text-sm">Belum ada barang. Daftar ini dipakai purchasing untuk membandingkan harga supplier.</p>
        @endif

        <div class="space-y-2">
            @foreach ($items as $index => $row)
                <div class="border-hairline grid grid-cols-1 items-end gap-3 rounded-lg border p-3 sm:grid-cols-12"
                     wire:key="item-{{ $index }}">
                    <div class="sm:col-span-4">
                        <label class="label">Produk</label>
                        <select wire:model="items.{{ $index }}.product_id" class="input">
                            <option value="">Pilih produk</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->label }}</option>
                            @endforeach
                        </select>
                        @error('items.'.$index.'.product_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="label">SKU Supplier</label>
                        <input wire:model="items.{{ $index }}.supplier_sku" class="input">
                    </div>

                    <div class="sm:col-span-1">
                        <label class="label">Satuan</label>
                        <select wire:model="items.{{ $index }}.unit_id" class="input">
                            <option value="">-</option>
                            @foreach ($units as $id => $code)
                                <option value="{{ $id }}">{{ $code }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="label">Harga Terakhir</label>
                        <input type="number" step="0.01" wire:model="items.{{ $index }}.last_price" class="input">
                        @error('items.'.$index.'.last_price') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-1">
                        <label class="label">Min Qty</label>
                        <input type="number" step="0.0001" wire:model="items.{{ $index }}.minimum_quantity" class="input">
                    </div>

                    <div class="sm:col-span-1">
                        <label class="label">Lead</label>
                        <input type="number" wire:model="items.{{ $index }}.lead_time_days" class="input">
                    </div>

                    <div class="flex items-center gap-1 sm:col-span-1">
                        <label class="flex items-center gap-1 text-xs" title="Supplier utama untuk produk ini">
                            <input type="checkbox" wire:model="items.{{ $index }}.is_preferred"
                                   class="rounded text-brand-500 focus:ring-brand-500">
                            Utama
                        </label>
                        <button type="button" wire:click="removeItemRow({{ $index }})"
                                class="btn-icon text-muted hover:bg-negative/10 hover:text-negative">
                            <x-icon name="trash" class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="card card-pad space-y-4">
        <p class="section-title">Catatan</p>

        <textarea wire:model="form.note" rows="2" class="input" placeholder="Catatan internal tentang supplier ini"></textarea>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model="form.is_active" class="rounded text-brand-500 focus:ring-brand-500">
            Supplier aktif
        </label>
    </div>

    <div class="flex items-center gap-2">
        <button type="button" wire:click="save" wire:loading.attr="disabled" class="btn-primary">
            <span wire:loading.remove wire:target="save">Simpan Supplier</span>
            <span wire:loading wire:target="save">Menyimpan...</span>
        </button>
        <a href="{{ route('suppliers.index') }}" wire:navigate class="btn-secondary">Batal</a>
    </div>
</div>
