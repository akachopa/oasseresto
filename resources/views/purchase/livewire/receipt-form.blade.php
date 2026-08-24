@php
    use App\Modules\Core\Support\Money;
@endphp

<div class="space-y-4">
    <div class="card card-pad space-y-4">
        <p class="section-title">Informasi Penerimaan</p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="sm:col-span-3">
                <label class="label" for="purchase_order_id">Purchase Order</label>
                <select id="purchase_order_id" wire:model.live="form.purchase_order_id" class="input">
                    <option value="">Tanpa PO (penerimaan langsung)</option>
                    @foreach ($orders as $order)
                        <option value="{{ $order->id }}">
                            {{ $order->number }} · {{ $order->supplier?->name }} ·
                            {{ $order->order_date->format('d/m/Y') }}
                        </option>
                    @endforeach
                </select>
                @error('form.purchase_order_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="supplier_id">Supplier</label>
                <select id="supplier_id" wire:model="form.supplier_id" class="input" required>
                    <option value="">Pilih supplier</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->label }}</option>
                    @endforeach
                </select>
                @error('form.supplier_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="warehouse_id">Gudang</label>
                <select id="warehouse_id" wire:model="form.warehouse_id" class="input" required>
                    <option value="">Pilih gudang</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->label }}</option>
                    @endforeach
                </select>
                @error('form.warehouse_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="receipt_date">Tanggal Terima</label>
                <input id="receipt_date" type="date" wire:model="form.receipt_date" class="input" required>
                @error('form.receipt_date') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="supplier_do_number">Nomor Surat Jalan Supplier</label>
                <input id="supplier_do_number" wire:model="form.supplier_do_number" class="input">
                @error('form.supplier_do_number') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="label" for="note">Catatan</label>
                <input id="note" wire:model="form.note" class="input" placeholder="Kondisi barang, nama pengantar, dan lain-lain">
            </div>
        </div>
    </div>

    <div class="card card-pad space-y-4">
        <div class="flex items-center justify-between gap-2">
            <p class="section-title">Barang Diterima</p>
            <button type="button" wire:click="addItemRow" class="btn-secondary">
                <x-icon name="plus" class="h-4 w-4" /> Tambah Baris
            </button>
        </div>

        @error('items') <p class="field-error">{{ $message }}</p> @enderror

        <div class="space-y-2">
            @foreach ($items as $index => $row)
                @php
                    $sisa = $row['purchase_order_item_id']
                        ? ($outstanding[(int) $row['purchase_order_item_id']] ?? null)
                        : null;
                @endphp

                <div class="border-hairline grid grid-cols-1 items-end gap-3 rounded-lg border p-3 sm:grid-cols-12"
                     wire:key="gr-item-{{ $index }}">
                    <div class="sm:col-span-3">
                        <label class="label">Produk</label>
                        <select wire:model.live="items.{{ $index }}.product_id" class="input"
                                @disabled($row['purchase_order_item_id'])>
                            <option value="">Pilih produk</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->label }}</option>
                            @endforeach
                        </select>
                        @if ($sisa !== null)
                            <p class="text-muted mt-1 text-xs">Sisa PO {{ Money::quantity($sisa) }}</p>
                        @endif
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
                        <label class="label">Diterima</label>
                        <input type="number" step="0.0001" wire:model="items.{{ $index }}.quantity"
                               class="input text-right">
                        @error('items.'.$index.'.quantity') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-1">
                        <label class="label">Ditolak</label>
                        <input type="number" step="0.0001" wire:model="items.{{ $index }}.rejected_base_quantity"
                               class="input text-right">
                        @error('items.'.$index.'.rejected_base_quantity') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="label">Harga Pokok</label>
                        <input type="number" step="0.01" wire:model="items.{{ $index }}.unit_cost"
                               class="input text-right">
                        @error('items.'.$index.'.unit_cost') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-1">
                        <label class="label">Batch</label>
                        <input wire:model="items.{{ $index }}.batch_number" class="input" placeholder="auto">
                    </div>

                    <div class="sm:col-span-1">
                        <label class="label">Expiry</label>
                        <input type="date" wire:model="items.{{ $index }}.expiry_date" class="input">
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
            Kolom ditolak memakai satuan dasar dan tidak masuk stok; barang tersebut diselesaikan lewat retur
            pembelian atau pengiriman ulang supplier.
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
        <a href="{{ route('purchase.receipts.index') }}" wire:navigate class="btn-ghost">Batal</a>
    </div>
</div>
