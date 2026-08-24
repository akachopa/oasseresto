@php
    use App\Modules\Core\Support\Money;
@endphp

<div class="space-y-4">
    <div class="card card-pad space-y-4">
        <p class="section-title">Informasi Order</p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="label" for="supplier_id">Supplier</label>
                <select id="supplier_id" wire:model.live="form.supplier_id" class="input" required>
                    <option value="">Pilih supplier</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->label }}</option>
                    @endforeach
                </select>
                @error('form.supplier_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="warehouse_id">Gudang Tujuan</label>
                <select id="warehouse_id" wire:model="form.warehouse_id" class="input" required>
                    <option value="">Pilih gudang</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->label }}</option>
                    @endforeach
                </select>
                @error('form.warehouse_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="payment_term">Termin Pembayaran</label>
                <select id="payment_term" wire:model="form.payment_term" class="input">
                    @foreach ($paymentTerms as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('form.payment_term') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="order_date">Tanggal PO</label>
                <input id="order_date" type="date" wire:model="form.order_date" class="input" required>
                @error('form.order_date') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="expected_date">Perkiraan Datang</label>
                <input id="expected_date" type="date" wire:model="form.expected_date" class="input">
                @error('form.expected_date') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="other_cost">Biaya Lain</label>
                <input id="other_cost" type="number" step="0.01" wire:model.live.debounce.400ms="form.other_cost"
                       class="input text-right">
                @error('form.other_cost') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model.live="form.is_tax_inclusive" class="rounded">
            Harga sudah termasuk pajak
        </label>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="note">Catatan Internal</label>
                <input id="note" wire:model="form.note" class="input">
            </div>

            <div>
                <label class="label" for="terms">Syarat untuk Supplier</label>
                <input id="terms" wire:model="form.terms" class="input"
                       placeholder="Contoh: pengiriman bertahap diperbolehkan">
            </div>
        </div>
    </div>

    <div class="card card-pad space-y-4">
        <div class="flex items-center justify-between gap-2">
            <p class="section-title">Barang</p>
            <button type="button" wire:click="addItemRow" class="btn-secondary">
                <x-icon name="plus" class="h-4 w-4" /> Tambah Baris
            </button>
        </div>

        @error('items') <p class="field-error">{{ $message }}</p> @enderror

        <div class="space-y-2">
            @foreach ($items as $index => $row)
                @php
                    $lineTotal = (float) ($row['quantity'] ?? 0) * (float) ($row['unit_price'] ?? 0);
                    $lineTotal -= $lineTotal * (float) ($row['discount_percent'] ?? 0) / 100;
                @endphp

                <div class="border-hairline grid grid-cols-1 items-end gap-3 rounded-lg border p-3 sm:grid-cols-12"
                     wire:key="po-item-{{ $index }}">
                    <div class="sm:col-span-3">
                        <label class="label">Produk</label>
                        <select wire:model.live="items.{{ $index }}.product_id" class="input">
                            <option value="">Pilih produk</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->label }}</option>
                            @endforeach
                        </select>
                        @error('items.'.$index.'.product_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-1">
                        <label class="label">Satuan</label>
                        <select wire:model="items.{{ $index }}.unit_id" class="input">
                            <option value="">-</option>
                            @foreach ($units as $id => $code)
                                <option value="{{ $id }}">{{ $code }}</option>
                            @endforeach
                        </select>
                        @error('items.'.$index.'.unit_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="label">Kuantitas</label>
                        <input type="number" step="0.0001" wire:model.live.debounce.400ms="items.{{ $index }}.quantity"
                               class="input text-right">
                        @error('items.'.$index.'.quantity') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="label">Harga</label>
                        <input type="number" step="0.01" wire:model.live.debounce.400ms="items.{{ $index }}.unit_price"
                               class="input text-right">
                        @error('items.'.$index.'.unit_price') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-1">
                        <label class="label">Disc %</label>
                        <input type="number" step="0.01" wire:model.live.debounce.400ms="items.{{ $index }}.discount_percent"
                               class="input text-right">
                        @error('items.'.$index.'.discount_percent') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="label">Pajak</label>
                        <select wire:model.live="items.{{ $index }}.tax_code_id" class="input">
                            <option value="">Tanpa pajak</option>
                            @foreach ($taxCodes as $taxCode)
                                <option value="{{ $taxCode->id }}">{{ $taxCode->code }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-1 flex items-center justify-between gap-1">
                        <span class="text-muted text-xs sm:hidden">{{ Money::compact($lineTotal) }}</span>
                        <button type="button" wire:click="removeItemRow({{ $index }})"
                                class="btn-icon text-muted hover:bg-negative/10 hover:text-negative">
                            <x-icon name="trash" class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <dl class="border-hairline ml-auto w-full max-w-xs space-y-1 border-t pt-3 text-sm sm:w-72">
            <div class="flex items-center justify-between">
                <dt class="text-muted">Subtotal</dt>
                <dd>{{ Money::rupiah($summary['subtotal']) }}</dd>
            </div>
            <div class="flex items-center justify-between">
                <dt class="text-muted">Diskon</dt>
                <dd>{{ Money::rupiah($summary['discount']) }}</dd>
            </div>
            <div class="flex items-center justify-between">
                <dt class="text-muted">Pajak</dt>
                <dd>{{ Money::rupiah($summary['tax']) }}</dd>
            </div>
            <div class="border-hairline flex items-center justify-between border-t pt-1 text-base font-semibold">
                <dt>Total</dt>
                <dd>{{ Money::rupiah($summary['total']) }}</dd>
            </div>
        </dl>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <button type="button" wire:click="save(false)" wire:loading.attr="disabled" class="btn-secondary">
            Simpan Draft
        </button>
        <button type="button" wire:click="save(true)" wire:loading.attr="disabled" class="btn-primary">
            <span wire:loading.remove wire:target="save">Simpan & Ajukan</span>
            <span wire:loading wire:target="save">Memproses...</span>
        </button>
        <a href="{{ route('purchase.orders.index') }}" wire:navigate class="btn-ghost">Batal</a>
    </div>
</div>
