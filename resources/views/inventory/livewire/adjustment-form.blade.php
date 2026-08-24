@php
    use App\Modules\Core\Support\Money;
@endphp

<div class="space-y-4">
    <div class="card card-pad space-y-4">
        <p class="section-title">Informasi Penyesuaian</p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="label" for="warehouse_id">Gudang</label>
                <select id="warehouse_id" wire:model.live="form.warehouse_id" class="input" required>
                    <option value="">Pilih gudang</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->label }}</option>
                    @endforeach
                </select>
                @error('form.warehouse_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="reason">Alasan</label>
                <select id="reason" wire:model="form.reason" class="input" required>
                    @foreach ($reasons as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('form.reason') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="adjustment_date">Tanggal</label>
                <input id="adjustment_date" type="date" wire:model="form.adjustment_date" class="input" required>
                @error('form.adjustment_date') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="label" for="note">Catatan</label>
            <input id="note" wire:model="form.note" class="input" placeholder="Penjelasan singkat penyesuaian">
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
                    $available = $row['product_id'] ? ($availability[(int) $row['product_id']] ?? 0) : null;
                    $rowBatches = $row['product_id'] ? ($batchOptions[(int) $row['product_id']] ?? []) : [];
                @endphp

                <div class="border-hairline grid grid-cols-1 items-end gap-3 rounded-lg border p-3 sm:grid-cols-12"
                     wire:key="adjustment-item-{{ $index }}">
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

                    <div class="sm:col-span-2">
                        <label class="label">Batch</label>
                        <select wire:model="items.{{ $index }}.batch_id" class="input">
                            <option value="">Otomatis (FEFO)</option>
                            @foreach ($rowBatches as $batch)
                                <option value="{{ $batch->id }}">
                                    {{ $batch->batch_number }} ({{ Money::quantity($batch->quantity) }})
                                </option>
                            @endforeach
                        </select>
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
                        <input type="number" step="0.0001" wire:model="items.{{ $index }}.quantity" class="input">
                        @if ($available !== null)
                            <p class="text-muted mt-1 text-xs">Saldo {{ Money::quantity($available) }}</p>
                        @endif
                        @error('items.'.$index.'.quantity') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="label">HPP Satuan</label>
                        <input type="number" step="0.01" wire:model="items.{{ $index }}.unit_cost" class="input">
                        @error('items.'.$index.'.unit_cost') <p class="field-error">{{ $message }}</p> @enderror
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

        <p class="text-muted text-xs">
            Kuantitas minus mengurangi stok, plus menambah stok. HPP diisi otomatis dari layer biaya gudang.
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <button type="button" wire:click="save(false)" wire:loading.attr="disabled" class="btn-secondary">
            Simpan Draft
        </button>
        <button type="button" wire:click="save(true)" wire:loading.attr="disabled" class="btn-primary">
            <span wire:loading.remove wire:target="save">Simpan & Posting</span>
            <span wire:loading wire:target="save">Memproses...</span>
        </button>
        <a href="{{ route('inventory.adjustments.index') }}" wire:navigate class="btn-ghost">Batal</a>
    </div>
</div>
