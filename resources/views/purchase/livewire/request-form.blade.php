@php
    use App\Modules\Core\Support\Money;
@endphp

<div class="space-y-4">
    <div class="card card-pad space-y-4">
        <p class="section-title">Informasi Permintaan</p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
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
                <label class="label" for="request_date">Tanggal Permintaan</label>
                <input id="request_date" type="date" wire:model="form.request_date" class="input" required>
                @error('form.request_date') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="needed_date">Dibutuhkan Tanggal</label>
                <input id="needed_date" type="date" wire:model="form.needed_date" class="input">
                @error('form.needed_date') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="priority">Prioritas</label>
                <select id="priority" wire:model="form.priority" class="input">
                    @foreach ($priorities as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('form.priority') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="label" for="note">Catatan</label>
            <input id="note" wire:model="form.note" class="input" placeholder="Alasan kebutuhan atau keterangan lain">
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
                <div class="border-hairline grid grid-cols-1 items-end gap-3 rounded-lg border p-3 sm:grid-cols-12"
                     wire:key="pr-item-{{ $index }}">
                    <div class="sm:col-span-4">
                        <label class="label">Produk</label>
                        <select wire:model.live="items.{{ $index }}.product_id" class="input">
                            <option value="">Pilih produk</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->label }}</option>
                            @endforeach
                        </select>
                        @error('items.'.$index.'.product_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
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
                        <label class="label">Estimasi Harga</label>
                        <input type="number" step="0.01" wire:model.live.debounce.400ms="items.{{ $index }}.estimated_price"
                               class="input text-right">
                        @error('items.'.$index.'.estimated_price') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-1">
                        <label class="label">Supplier</label>
                        <select wire:model="items.{{ $index }}.suggested_supplier_id" class="input">
                            <option value="">-</option>
                            @foreach ($suppliers as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-1">
                        <button type="button" wire:click="removeItemRow({{ $index }})"
                                class="btn-icon text-muted hover:bg-negative/10 hover:text-negative">
                            <x-icon name="trash" class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="border-hairline flex items-center justify-end gap-3 border-t pt-3">
            <span class="text-muted text-sm">Estimasi total</span>
            <span class="text-base font-semibold">{{ Money::rupiah($estimatedTotal) }}</span>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <button type="button" wire:click="save(false)" wire:loading.attr="disabled" class="btn-secondary">
            Simpan Draft
        </button>
        <button type="button" wire:click="save(true)" wire:loading.attr="disabled" class="btn-primary">
            <span wire:loading.remove wire:target="save">Simpan & Ajukan</span>
            <span wire:loading wire:target="save">Memproses...</span>
        </button>
        <a href="{{ route('purchase.requests.index') }}" wire:navigate class="btn-ghost">Batal</a>
    </div>
</div>
