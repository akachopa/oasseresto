<div class="max-w-4xl space-y-4">
    <div class="card card-pad space-y-4">
        <p class="section-title">Identitas</p>

        <div class="form-grid">
            <div>
                <label class="label" for="code">Kode Customer</label>
                <input id="code" wire:model="form.code" class="input" required>
                @error('form.code') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="type">Tipe</label>
                <select id="type" wire:model="form.type" class="input">
                    @foreach (['retail' => 'Retail', 'wholesale' => 'Grosir', 'project' => 'Proyek', 'institution' => 'Instansi'] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-2">
                <label class="label" for="name">Nama Customer</label>
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
                <label class="label" for="address">Alamat Utama</label>
                <textarea id="address" wire:model="form.address" rows="2" class="input"></textarea>
            </div>

            <div>
                <label class="label" for="city">Kota</label>
                <input id="city" wire:model="form.city" class="input">
            </div>

            <div>
                <label class="label" for="sales_area">Area Sales</label>
                <input id="sales_area" wire:model="form.sales_area" class="input">
            </div>
        </div>
    </div>

    <div class="card card-pad space-y-4">
        <p class="section-title">Harga & Penanganan</p>

        <div class="form-grid">
            <div>
                <label class="label" for="customer_group_id">Grup Customer</label>
                <select id="customer_group_id" wire:model.live="form.customer_group_id" class="input">
                    <option value="">-</option>
                    @foreach ($groups as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="price_level_id">Level Harga</label>
                <select id="price_level_id" wire:model="form.price_level_id" class="input">
                    <option value="">Ikut grup</option>
                    @foreach ($priceLevels as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="branch_id">Cabang Pengelola</label>
                <select id="branch_id" wire:model="form.branch_id" class="input">
                    <option value="">Semua cabang</option>
                    @foreach ($branches as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="salesman_id">Salesman</label>
                <select id="salesman_id" wire:model="form.salesman_id" class="input">
                    <option value="">-</option>
                    @foreach ($salesmen as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="preferred_delivery">Preferensi Pengiriman</label>
                <select id="preferred_delivery" wire:model="form.preferred_delivery" class="input">
                    <option value="">-</option>
                    <option value="pickup">Ambil Sendiri</option>
                    <option value="delivery">Dikirim Armada Sendiri</option>
                    <option value="expedition">Ekspedisi</option>
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

            <div>
                <label class="label" for="tax_number">NPWP</label>
                <input id="tax_number" wire:model="form.tax_number" class="input">
            </div>
        </div>
    </div>

    <div class="card card-pad space-y-4">
        <p class="section-title">Termin & Kredit</p>

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
                <label class="label" for="credit_limit">Credit Limit</label>
                <input id="credit_limit" type="number" step="0.01" wire:model="form.credit_limit" class="input"
                       @disabled(! $this->canManageCredit())>
                @if (! $this->canManageCredit())
                    <p class="text-muted mt-1 text-xs">Hanya finance yang boleh mengubah credit limit.</p>
                @endif
                @error('form.credit_limit') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div class="card card-pad space-y-4">
        <div class="flex items-center justify-between gap-2">
            <p class="section-title">Alamat Kirim</p>
            <button type="button" wire:click="addAddressRow" class="btn-secondary">
                <x-icon name="plus" class="h-4 w-4" /> Tambah Alamat
            </button>
        </div>

        @if ($addresses === [])
            <p class="text-muted text-sm">Belum ada alamat kirim tambahan. Alamat utama tetap dipakai.</p>
        @endif

        <div class="space-y-2">
            @foreach ($addresses as $index => $row)
                <div class="border-hairline grid grid-cols-1 items-end gap-3 rounded-lg border p-3 sm:grid-cols-12"
                     wire:key="address-{{ $index }}">
                    <div class="sm:col-span-3">
                        <label class="label">Nama Alamat</label>
                        <input wire:model="addresses.{{ $index }}.label" class="input" placeholder="Gudang / Toko">
                        @error('addresses.'.$index.'.label') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-4">
                        <label class="label">Alamat</label>
                        <input wire:model="addresses.{{ $index }}.address" class="input">
                        @error('addresses.'.$index.'.address') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="label">Kota</label>
                        <input wire:model="addresses.{{ $index }}.city" class="input">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="label">PIC / Telepon</label>
                        <input wire:model="addresses.{{ $index }}.phone" class="input">
                    </div>

                    <div class="flex items-center gap-1 sm:col-span-1">
                        <button type="button" wire:click="markDefaultAddress({{ $index }})"
                                title="Jadikan alamat utama"
                                class="btn-icon {{ ($row['is_default'] ?? false) ? 'text-brand-600' : 'text-muted' }} hover:bg-panel-soft">
                            <x-icon name="check" class="h-4 w-4" />
                        </button>
                        <button type="button" wire:click="removeAddressRow({{ $index }})"
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

        <textarea wire:model="form.note" rows="2" class="input" placeholder="Catatan internal tentang customer ini"></textarea>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model="form.is_active" class="rounded text-brand-500 focus:ring-brand-500">
            Customer aktif
        </label>
    </div>

    <div class="flex items-center gap-2">
        <button type="button" wire:click="save" wire:loading.attr="disabled" class="btn-primary">
            <span wire:loading.remove wire:target="save">Simpan Customer</span>
            <span wire:loading wire:target="save">Menyimpan...</span>
        </button>
        <a href="{{ route('customers.index') }}" wire:navigate class="btn-secondary">Batal</a>
    </div>
</div>
