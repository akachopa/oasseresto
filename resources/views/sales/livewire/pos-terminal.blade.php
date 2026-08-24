@php
    use App\Modules\Core\Support\Money;
@endphp

<div class="grid gap-4 lg:grid-cols-5">
    <div class="space-y-4 lg:col-span-3">
        <div class="card card-pad space-y-3">
            <div>
                <label class="label" for="pos-search">Cari Produk</label>
                <input id="pos-search" wire:model.live.debounce.300ms="search" class="input"
                       placeholder="Ketik SKU, nama, atau scan barcode" autofocus>
            </div>

            @if ($results->isNotEmpty())
                <div class="divide-hairline divide-y">
                    @foreach ($results as $product)
                        <button type="button" wire:click="addProduct({{ $product->id }})"
                                class="hover:bg-panel-soft flex w-full items-center justify-between gap-3 px-1 py-2 text-left">
                            <span class="min-w-0">
                                <span class="block truncate font-medium">{{ $product->name }}</span>
                                <span class="text-muted block font-mono text-xs">{{ $product->sku }}</span>
                            </span>
                            <span class="text-right text-xs">
                                <span class="block font-medium">{{ Money::rupiah($product->base_price) }}</span>
                                <span class="text-muted">stok {{ Money::quantity($product->available_quantity) }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            @elseif (mb_strlen($search) > 0 && mb_strlen($search) < 2)
                <p class="text-muted text-xs">Ketik minimal dua karakter.</p>
            @endif
        </div>

        <div class="card card-pad space-y-3">
            <div class="flex items-center justify-between gap-2">
                <p class="section-title">Keranjang</p>

                @if ($cart !== [])
                    <button type="button" wire:click="clearCart" class="btn-ghost text-xs">Kosongkan</button>
                @endif
            </div>

            @error('cart') <p class="field-error">{{ $message }}</p> @enderror

            @if ($cart === [])
                <x-empty-state icon="cash-register" title="Keranjang kosong"
                               message="Cari produk di atas untuk menambahkan ke keranjang." />
            @else
                <div class="oasse-table-wrap">
                    <table class="table-oasse">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Harga</th>
                                <th class="text-right">Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cart as $index => $line)
                                <tr wire:key="pos-line-{{ $index }}">
                                    <td>
                                        <span class="font-medium">{{ $line['name'] }}</span>
                                        <span class="text-muted block font-mono text-xs">
                                            {{ $line['sku'] }} · {{ $line['unit'] }}
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <input type="number" step="0.0001" min="0"
                                               value="{{ $line['quantity'] }}"
                                               wire:change="updateQuantity({{ $index }}, $event.target.value)"
                                               class="input w-20 text-right">
                                    </td>
                                    <td class="text-right">{{ Money::rupiah($line['unit_price']) }}</td>
                                    <td class="text-right font-medium">
                                        {{ Money::rupiah($line['quantity'] * $line['unit_price']) }}
                                    </td>
                                    <td class="text-right">
                                        <button type="button" wire:click="removeLine({{ $index }})"
                                                class="btn-icon text-muted hover:bg-negative/10 hover:text-negative">
                                            <x-icon name="trash" class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="space-y-4 lg:col-span-2">
        <div class="card card-pad space-y-3">
            <p class="section-title">Pembayaran</p>

            <div>
                <label class="label" for="pos-customer">Customer</label>
                <select id="pos-customer" wire:model.live="customerId" class="input">
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->label }}</option>
                    @endforeach
                </select>
                @error('customerId') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="pos-method">Metode</label>
                <select id="pos-method" wire:model="paymentMethod" class="input">
                    <option value="cash">Tunai</option>
                    <option value="transfer">Transfer</option>
                    <option value="card">Kartu</option>
                </select>
            </div>

            <div class="border-hairline flex items-center justify-between border-t pt-3">
                <span class="text-muted text-sm">Total</span>
                <span class="text-xl font-semibold">{{ Money::rupiah($total) }}</span>
            </div>

            <div>
                <label class="label" for="pos-paid">Dibayar</label>
                <input id="pos-paid" type="number" step="0.01" min="0" wire:model.live="paidAmount"
                       class="input text-right text-lg">
                @error('paidAmount') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-between text-sm">
                <span class="text-muted">Kembalian</span>
                <span class="font-medium">{{ Money::rupiah(max(0, $paidAmount - $total)) }}</span>
            </div>

            <button type="button" wire:click="checkout" wire:loading.attr="disabled"
                    class="btn-primary w-full justify-center" @disabled($cart === [])>
                <span wire:loading.remove wire:target="checkout">Bayar & Cetak</span>
                <span wire:loading wire:target="checkout">Memproses...</span>
            </button>
        </div>

        @if ($lastInvoice)
            <div class="card card-pad space-y-2">
                <p class="section-title">Transaksi Terakhir</p>
                <p class="font-mono text-xs">{{ $lastInvoice->number }}</p>

                <dl class="divide-hairline divide-y text-sm">
                    <div class="flex items-center justify-between py-1">
                        <dt class="text-muted">Total</dt>
                        <dd class="font-medium">{{ Money::rupiah($lastInvoice->total) }}</dd>
                    </div>
                    <div class="flex items-center justify-between py-1">
                        <dt class="text-muted">Dibayar</dt>
                        <dd class="font-medium">{{ Money::rupiah($lastInvoice->paid_amount) }}</dd>
                    </div>
                    <div class="flex items-center justify-between py-1">
                        <dt class="text-muted">Kembalian</dt>
                        <dd class="font-medium">{{ Money::rupiah($lastChange) }}</dd>
                    </div>
                </dl>

                <a href="{{ route('sales.invoices.print', $lastInvoice) }}" target="_blank"
                   class="btn-secondary w-full justify-center">
                    <x-icon name="document" class="h-4 w-4" /> Cetak Struk
                </a>
            </div>
        @endif

        <div class="card card-pad space-y-2">
            <p class="section-title">Shift Berjalan</p>

            <dl class="divide-hairline divide-y text-sm">
                @foreach ([
                    'Modal Awal' => Money::rupiah($shift->opening_cash),
                    'Penjualan Tunai' => Money::rupiah($shift->cash_sales),
                    'Non Tunai' => Money::rupiah($shift->non_cash_sales),
                    'Kredit' => Money::rupiah($shift->credit_sales),
                    'Perkiraan Kas' => Money::rupiah($shift->expected_cash),
                    'Jumlah Transaksi' => (string) $shift->transaction_count,
                ] as $label => $value)
                    <div class="flex items-center justify-between py-1">
                        <dt class="text-muted">{{ $label }}</dt>
                        <dd class="font-medium">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>
</div>
