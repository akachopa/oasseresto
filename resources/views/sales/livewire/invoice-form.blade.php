@php
    use App\Modules\Core\Support\Money;
@endphp

<div class="space-y-4">
    <div class="card card-pad space-y-4">
        <p class="section-title">Informasi Invoice</p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="sm:col-span-3">
                <label class="label" for="delivery_id">Surat Jalan</label>
                <select id="delivery_id" wire:model.live="form.delivery_id" class="input">
                    <option value="">Tanpa surat jalan (tagihan manual)</option>
                    @foreach ($deliveries as $delivery)
                        <option value="{{ $delivery->id }}">
                            {{ $delivery->number }} · {{ $delivery->customer?->name }} ·
                            {{ $delivery->delivery_date->format('d/m/Y') }}
                        </option>
                    @endforeach
                </select>
                @error('form.delivery_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="customer_id">Customer</label>
                <select id="customer_id" wire:model.live="form.customer_id" class="input" required>
                    <option value="">Pilih customer</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->label }}</option>
                    @endforeach
                </select>
                @error('form.customer_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="payment_term">Termin</label>
                <select id="payment_term" wire:model.live="form.payment_term" class="input">
                    @foreach ($paymentTerms as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('form.payment_term') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="shipping_cost">Biaya Kirim</label>
                <input id="shipping_cost" type="number" step="0.01"
                       wire:model.live.debounce.400ms="form.shipping_cost" class="input text-right">
            </div>

            <div>
                <label class="label" for="invoice_date">Tanggal Invoice</label>
                <input id="invoice_date" type="date" wire:model.live="form.invoice_date" class="input" required>
                @error('form.invoice_date') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="due_date">Jatuh Tempo</label>
                <input id="due_date" type="date" wire:model="form.due_date" class="input">
                @error('form.due_date') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="paid_amount">Dibayar Saat Posting</label>
                <input id="paid_amount" type="number" step="0.01" wire:model="paidAmount" class="input text-right">
                @error('paidAmount') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-3">
                <label class="label" for="note">Catatan</label>
                <input id="note" wire:model="form.note" class="input">
            </div>
        </div>
    </div>

    <div class="card card-pad space-y-4">
        <div class="flex items-center justify-between gap-2">
            <p class="section-title">Baris Tagihan</p>
            <button type="button" wire:click="addItemRow" class="btn-secondary">
                <x-icon name="plus" class="h-4 w-4" /> Tambah Baris
            </button>
        </div>

        @error('items') <p class="field-error">{{ $message }}</p> @enderror

        <div class="space-y-2">
            @foreach ($items as $index => $row)
                <div class="border-hairline grid grid-cols-1 items-end gap-3 rounded-lg border p-3 sm:grid-cols-12"
                     wire:key="si-item-{{ $index }}">
                    <div class="sm:col-span-4">
                        <label class="label">Produk</label>
                        <select wire:model.live="items.{{ $index }}.product_id" class="input"
                                @disabled($row['delivery_item_id'])>
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
                        <label class="label">Diskon</label>
                        <input type="number" step="0.01" wire:model.live.debounce.400ms="items.{{ $index }}.discount_amount"
                               class="input text-right">
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

        @can('sales.post')
            <button type="button" wire:click="save(true)" wire:loading.attr="disabled" class="btn-primary">
                <span wire:loading.remove wire:target="save">Simpan & Posting</span>
                <span wire:loading wire:target="save">Memproses...</span>
            </button>
        @endcan

        <a href="{{ route('sales.invoices.index') }}" wire:navigate class="btn-ghost">Batal</a>
    </div>
</div>
