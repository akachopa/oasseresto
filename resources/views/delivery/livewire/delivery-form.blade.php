@php
    use App\Modules\Core\Support\Money;
@endphp

<div class="space-y-4">
    <div class="card card-pad space-y-4">
        <p class="section-title">Informasi Pengiriman</p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="sm:col-span-3">
                <label class="label" for="sales_order_id">Sales Order</label>
                <select id="sales_order_id" wire:model.live="form.sales_order_id" class="input">
                    <option value="">Tanpa sales order</option>
                    @foreach ($orders as $order)
                        <option value="{{ $order->id }}">
                            {{ $order->number }} · {{ $order->customer?->name }} ·
                            {{ $order->order_date->format('d/m/Y') }}
                        </option>
                    @endforeach
                </select>
                @error('form.sales_order_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="warehouse_id">Gudang Asal</label>
                <select id="warehouse_id" wire:model="form.warehouse_id" class="input" required>
                    <option value="">Pilih gudang</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->label }}</option>
                    @endforeach
                </select>
                @error('form.warehouse_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="delivery_date">Tanggal Kirim</label>
                <input id="delivery_date" type="date" wire:model="form.delivery_date" class="input" required>
                @error('form.delivery_date') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="driver_name">Nama Driver</label>
                <input id="driver_name" wire:model="form.driver_name" class="input">
            </div>

            <div>
                <label class="label" for="vehicle_number">Nomor Kendaraan</label>
                <input id="vehicle_number" wire:model="form.vehicle_number" class="input">
            </div>

            <div class="sm:col-span-2">
                <label class="label" for="shipping_address">Alamat Kirim</label>
                <input id="shipping_address" wire:model="form.shipping_address" class="input"
                       placeholder="Kosongkan untuk memakai alamat customer">
            </div>

            <div class="sm:col-span-3">
                <label class="label" for="note">Catatan</label>
                <input id="note" wire:model="form.note" class="input">
            </div>
        </div>

        @error('form.customer_id') <p class="field-error">{{ $message }}</p> @enderror
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
                    $sisa = $row['sales_order_item_id']
                        ? ($outstanding[(int) $row['sales_order_item_id']] ?? null)
                        : null;
                @endphp

                <div class="border-hairline grid grid-cols-1 items-end gap-3 rounded-lg border p-3 sm:grid-cols-12"
                     wire:key="do-item-{{ $index }}">
                    <div class="sm:col-span-6">
                        <label class="label">Produk</label>
                        <select wire:model.live="items.{{ $index }}.product_id" class="input"
                                @disabled($row['sales_order_item_id'])>
                            <option value="">Pilih produk</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->label }}</option>
                            @endforeach
                        </select>
                        @if ($sisa !== null)
                            <p class="text-muted mt-1 text-xs">Sisa order {{ Money::quantity($sisa) }}</p>
                        @endif
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

                    <div class="sm:col-span-3">
                        <label class="label">Kuantitas</label>
                        <input type="number" step="0.0001" wire:model="items.{{ $index }}.quantity"
                               class="input text-right">
                        @error('items.'.$index.'.quantity') <p class="field-error">{{ $message }}</p> @enderror
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
            Kuantitas di sini adalah rencana kirim. Yang benar-benar keluar ditentukan pada tahap picking.
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <button type="button" wire:click="save" wire:loading.attr="disabled" class="btn-primary">
            <span wire:loading.remove wire:target="save">Simpan Surat Jalan</span>
            <span wire:loading wire:target="save">Memproses...</span>
        </button>
        <a href="{{ route('delivery.orders.index') }}" wire:navigate class="btn-ghost">Batal</a>
    </div>
</div>
