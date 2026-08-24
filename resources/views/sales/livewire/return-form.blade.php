@php
    use App\Modules\Core\Support\Money;
@endphp

<div class="space-y-4">
    <div class="card card-pad space-y-4">
        <p class="section-title">Informasi Retur</p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="sm:col-span-3">
                <label class="label" for="sales_invoice_id">Invoice Penjualan</label>
                <select id="sales_invoice_id" wire:model.live="form.sales_invoice_id" class="input">
                    <option value="">Tanpa invoice (retur langsung)</option>
                    @foreach ($invoices as $invoice)
                        <option value="{{ $invoice->id }}">
                            {{ $invoice->number }} · {{ $invoice->customer?->name }} ·
                            {{ $invoice->invoice_date->format('d/m/Y') }}
                        </option>
                    @endforeach
                </select>
                @error('form.sales_invoice_id') <p class="field-error">{{ $message }}</p> @enderror
                <p class="text-muted mt-1 text-xs">
                    Credit note hanya bisa mengurangi piutang bila retur terhubung ke invoice.
                </p>
            </div>

            <div>
                <label class="label" for="customer_id">Customer</label>
                <select id="customer_id" wire:model="form.customer_id" class="input" required>
                    <option value="">Pilih customer</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->label }}</option>
                    @endforeach
                </select>
                @error('form.customer_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="warehouse_id">Gudang Penerima</label>
                <select id="warehouse_id" wire:model="form.warehouse_id" class="input" required>
                    <option value="">Pilih gudang</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->label }}</option>
                    @endforeach
                </select>
                @error('form.warehouse_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="return_date">Tanggal Retur</label>
                <input id="return_date" type="date" wire:model="form.return_date" class="input" required>
                @error('form.return_date') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="reason">Alasan</label>
                <select id="reason" wire:model.live="form.reason" class="input">
                    @foreach ($reasons as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('form.reason') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="settlement">Penyelesaian</label>
                <select id="settlement" wire:model="form.settlement" class="input">
                    @foreach ($settlements as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('form.settlement') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-end">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model.live="form.restock" class="rounded">
                    Masukkan kembali ke stok
                </label>
            </div>

            <div class="sm:col-span-3">
                <label class="label" for="note">Catatan</label>
                <input id="note" wire:model="form.note" class="input">
            </div>
        </div>
    </div>

    <div class="card card-pad space-y-4">
        <div class="flex items-center justify-between gap-2">
            <p class="section-title">Barang Dikembalikan</p>
            <button type="button" wire:click="addItemRow" class="btn-secondary">
                <x-icon name="plus" class="h-4 w-4" /> Tambah Baris
            </button>
        </div>

        @error('items') <p class="field-error">{{ $message }}</p> @enderror

        <div class="space-y-2">
            @foreach ($items as $index => $row)
                @php
                    $limit = $row['sales_invoice_item_id']
                        ? ($returnable[(int) $row['sales_invoice_item_id']] ?? null)
                        : null;
                @endphp

                <div class="border-hairline grid grid-cols-1 items-end gap-3 rounded-lg border p-3 sm:grid-cols-12"
                     wire:key="sr-item-{{ $index }}">
                    <div class="sm:col-span-5">
                        <label class="label">Produk</label>
                        <select wire:model.live="items.{{ $index }}.product_id" class="input"
                                @disabled($row['sales_invoice_item_id'])>
                            <option value="">Pilih produk</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->label }}</option>
                            @endforeach
                        </select>
                        @if ($limit !== null)
                            <p class="text-muted mt-1 text-xs">Bisa diretur {{ Money::quantity($limit) }}</p>
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
                        <label class="label">Pajak</label>
                        <select wire:model.live="items.{{ $index }}.tax_code_id" class="input">
                            <option value="">-</option>
                            @foreach ($taxCodes as $taxCode)
                                <option value="{{ $taxCode->id }}">{{ $taxCode->code }}</option>
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

        <dl class="border-hairline ml-auto w-full max-w-xs space-y-1 border-t pt-3 text-sm sm:w-72">
            <div class="flex items-center justify-between">
                <dt class="text-muted">Subtotal</dt>
                <dd>{{ Money::rupiah($summary['subtotal']) }}</dd>
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
            <span wire:loading.remove wire:target="save">Simpan & Posting</span>
            <span wire:loading wire:target="save">Memproses...</span>
        </button>
        <a href="{{ route('sales.returns.index') }}" wire:navigate class="btn-ghost">Batal</a>
    </div>
</div>
