@php
    use App\Modules\Core\Support\Money;
    use App\Modules\Inventory\Models\Batch;
    use App\Modules\Inventory\Services\StockService;

    $stock = app(StockService::class);
@endphp

<x-layouts.app :title="'Picking '.$delivery->number">
    <x-page-header :title="'Picking '.$delivery->number"
                   :subtitle="$delivery->customer?->name.' · '.$delivery->warehouse?->name"
                   :back="route('delivery.picking.index')" />

    <form method="POST" action="{{ route('delivery.picking.store', $delivery) }}" class="space-y-4">
        @csrf

        <div class="card card-pad space-y-3">
            <p class="section-title">Barang Disiapkan</p>

            <div class="space-y-3">
                @foreach ($delivery->items as $item)
                    @php
                        $available = $stock->onHand((int) $item->product_id, (int) $delivery->warehouse_id);
                        $batches = $item->product?->track_batch
                            ? Batch::where('product_id', $item->product_id)
                                ->where('warehouse_id', $delivery->warehouse_id)
                                ->available()
                                ->fefo()
                                ->get()
                            : collect();
                    @endphp

                    <div class="border-hairline grid grid-cols-1 items-end gap-3 rounded-lg border p-3 sm:grid-cols-12">
                        <div class="sm:col-span-5">
                            <p class="font-medium">{{ $item->product?->name }}</p>
                            <p class="text-muted font-mono text-xs">{{ $item->product?->sku }}</p>
                            <p class="text-muted mt-1 text-xs">
                                Diminta {{ Money::quantity($item->base_quantity) }}
                                {{ $item->product?->baseUnit?->code }} · stok gudang {{ Money::quantity($available) }}
                            </p>
                        </div>

                        @if ($batches->isNotEmpty())
                            <div class="sm:col-span-4">
                                <label class="label" for="batch-{{ $item->id }}">Batch</label>
                                <select id="batch-{{ $item->id }}" name="batch[{{ $item->id }}]" class="input">
                                    <option value="">Otomatis (FEFO)</option>
                                    @foreach ($batches as $batch)
                                        <option value="{{ $batch->id }}" @selected($item->batch_id === $batch->id)>
                                            {{ $batch->batch_number }} ({{ Money::quantity($batch->quantity) }})
                                            @if ($batch->expiry_date) · exp {{ $batch->expiry_date->format('d/m/y') }} @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="{{ $batches->isNotEmpty() ? 'sm:col-span-3' : 'sm:col-span-7' }}">
                            <label class="label" for="picked-{{ $item->id }}">Berhasil Disiapkan</label>
                            <input id="picked-{{ $item->id }}" type="number" step="0.0001" min="0"
                                   max="{{ $item->base_quantity }}"
                                   name="picked[{{ $item->id }}]"
                                   value="{{ $item->picked_base_quantity > 0 ? $item->picked_base_quantity : $item->base_quantity }}"
                                   class="input text-right">
                        </div>
                    </div>
                @endforeach
            </div>

            <p class="text-muted text-xs">
                Kuantitas memakai satuan dasar. Kekurangan tetap terbuka di sales order sehingga bisa dikirim menyusul.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="btn-primary">
                <x-icon name="check" class="h-4 w-4" /> Selesai Picking
            </button>
            <a href="{{ route('delivery.orders.detail', $delivery) }}" wire:navigate class="btn-ghost">Batal</a>
        </div>
    </form>
</x-layouts.app>
