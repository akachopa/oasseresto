@php
    use App\Modules\Core\Support\Money;

    $creditBorder = match ($credit?->color()) {
        'success' => 'border-l-positive',
        'danger' => 'border-l-negative',
        default => 'border-l-caution',
    };
@endphp

<div class="space-y-4">
    @if ($credit)
        <div class="card card-pad border-l-4 {{ $creditBorder }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="section-title">Kondisi Kredit Customer</p>
                    <p class="text-muted mt-1 text-sm">
                        {{ $credit->message ?? 'Eksposur masih dalam batas yang diizinkan.' }}
                    </p>
                </div>

                <span class="badge-{{ $credit->color() }}">{{ $credit->label() }}</span>
            </div>

            <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                <div>
                    <dt class="text-muted text-xs uppercase">Credit Limit</dt>
                    <dd class="font-medium">{{ Money::rupiah($credit->creditLimit) }}</dd>
                </div>
                <div>
                    <dt class="text-muted text-xs uppercase">Terpakai</dt>
                    <dd class="font-medium">{{ Money::rupiah($credit->outstanding) }}</dd>
                </div>
                <div>
                    <dt class="text-muted text-xs uppercase">Sisa</dt>
                    <dd class="font-medium">{{ Money::rupiah($credit->available) }}</dd>
                </div>
                <div>
                    <dt class="text-muted text-xs uppercase">Jatuh Tempo</dt>
                    <dd class="font-medium">{{ Money::rupiah($credit->overdueAmount) }}</dd>
                </div>
            </dl>
        </div>
    @endif

    <div class="card card-pad space-y-4">
        <p class="section-title">Informasi Order</p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
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
                <label class="label" for="customer_address_id">Alamat Kirim</label>
                <select id="customer_address_id" wire:model="form.customer_address_id" class="input">
                    <option value="">Alamat utama customer</option>
                    @foreach ($addresses as $address)
                        <option value="{{ $address->id }}">{{ $address->label ?? $address->address }}</option>
                    @endforeach
                </select>
            </div>

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
                <label class="label" for="order_date">Tanggal Order</label>
                <input id="order_date" type="date" wire:model="form.order_date" class="input" required>
                @error('form.order_date') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="requested_delivery_date">Minta Dikirim</label>
                <input id="requested_delivery_date" type="date" wire:model="form.requested_delivery_date" class="input">
                @error('form.requested_delivery_date') <p class="field-error">{{ $message }}</p> @enderror
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
                @error('form.shipping_cost') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="label" for="note">Catatan</label>
                <input id="note" wire:model="form.note" class="input">
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model.live="form.is_tax_inclusive" class="rounded">
            Harga termasuk pajak
        </label>
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
                    $margin = $margins[$index] ?? null;
                @endphp

                <div class="border-hairline grid grid-cols-1 items-end gap-3 rounded-lg border p-3 sm:grid-cols-12"
                     wire:key="so-item-{{ $index }}">
                    <div class="sm:col-span-4">
                        <label class="label">Produk</label>
                        <select wire:model.live="items.{{ $index }}.product_id" class="input">
                            <option value="">Pilih produk</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->label }}</option>
                            @endforeach
                        </select>
                        @if ($available !== null)
                            <p class="text-muted mt-1 text-xs">Bisa dijanjikan {{ Money::quantity($available) }}</p>
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
                        @if ($margin)
                            <p class="mt-1 text-xs {{ $margin['requires_approval'] ? 'text-negative' : 'text-muted' }}">
                                Margin {{ Money::percent($margin['margin_percent']) }}
                                (min {{ Money::percent($margin['minimum']) }})
                            </p>
                        @endif
                        @error('items.'.$index.'.unit_price') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-1">
                        <label class="label">Disc %</label>
                        <input type="number" step="0.01" wire:model.live.debounce.400ms="items.{{ $index }}.discount_percent"
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
            <div class="flex items-center justify-between">
                <dt class="text-muted">Biaya Kirim</dt>
                <dd>{{ Money::rupiah($form['shipping_cost'] ?? 0) }}</dd>
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
        <a href="{{ route('sales.orders.index') }}" wire:navigate class="btn-ghost">Batal</a>
    </div>
</div>
