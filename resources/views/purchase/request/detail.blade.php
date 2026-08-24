@php
    use App\Modules\Core\Enums\DocumentStatus;
    use App\Modules\Core\Support\Money;

    $outstanding = $request->outstandingBaseQuantity();
@endphp

<x-layouts.app :title="'Purchase Request '.$request->number">
    <x-page-header :title="$request->number"
                   :subtitle="$request->warehouse?->name.' · prioritas '.strtolower($request->priorityLabel())"
                   :back="route('purchase.requests.index')">
        <x-slot:actions>
            <x-status-badge :status="$request->status" />

            @if ($request->status->isEditable())
                @can('purchase.edit')
                    <a href="{{ route('purchase.requests.edit', $request) }}" wire:navigate class="btn-secondary">
                        <x-icon name="pencil" class="h-4 w-4" /> Ubah
                    </a>
                @endcan

                @can('purchase.create')
                    <form method="POST" action="{{ route('purchase.requests.submit', $request) }}">
                        @csrf
                        <button type="submit" class="btn-primary">
                            <x-icon name="check" class="h-4 w-4" /> Ajukan Approval
                        </button>
                    </form>
                @endcan
            @endif

            @if ($request->status === DocumentStatus::Submitted)
                @can('purchase.approve')
                    <form method="POST" action="{{ route('purchase.requests.approve', $request) }}">
                        @csrf
                        <button type="submit" class="btn-primary">
                            <x-icon name="check" class="h-4 w-4" /> Setujui
                        </button>
                    </form>
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Tanggal" :value="$request->request_date->format('d/m/Y')"
               :hint="'Diminta '.($request->creator?->name ?? '-')" />
        <x-kpi label="Dibutuhkan" :value="$request->needed_date?->format('d/m/Y') ?? '-'" />
        <x-kpi label="Estimasi Nilai" :value="Money::compact($request->estimated_total)" />
        <x-kpi label="Belum Dipesan" :value="Money::quantity($outstanding)"
               :tone="$outstanding > 0 ? 'caution' : 'positive'" hint="dalam satuan dasar" />
    </div>

    @if ($request->rejection_reason)
        <div class="card card-pad border-l-negative mt-4 border-l-4">
            <p class="section-title">Alasan Penolakan</p>
            <p class="mt-1 text-sm">{{ $request->rejection_reason }}</p>
        </div>
    @endif

    <div class="card card-pad mt-4 space-y-3">
        <p class="section-title">Barang Diminta</p>

        <div class="oasse-table-wrap">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Saran Supplier</th>
                        <th class="text-right">Diminta</th>
                        <th class="text-right">Sudah Dipesan</th>
                        <th class="text-right">Estimasi Harga</th>
                        <th class="text-right">Estimasi Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($request->items as $item)
                        <tr>
                            <td class="font-medium">
                                {{ $item->product?->name }}
                                <span class="text-muted block font-mono text-xs">{{ $item->product?->sku }}</span>
                            </td>
                            <td>{{ $item->suggestedSupplier?->name ?: '-' }}</td>
                            <td class="text-right">
                                {{ Money::quantity($item->quantity) }} {{ $item->unit?->code }}
                            </td>
                            <td class="text-right">
                                {{ Money::quantity($item->ordered_base_quantity) }}
                                @if ($item->outstandingBaseQuantity() > 0 && $request->isOrderable())
                                    <span class="badge-warning ml-1">
                                        sisa {{ Money::quantity($item->outstandingBaseQuantity()) }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-right">{{ Money::rupiah($item->estimated_price) }}</td>
                            <td class="text-right">{{ Money::rupiah($item->estimatedTotal()) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($request->note)
            <p class="text-muted border-hairline border-t pt-3 text-sm">{{ $request->note }}</p>
        @endif
    </div>

    @if ($request->isOrderable() && $outstanding > 0)
        @can('purchase.create')
            <div class="card card-pad mt-4 space-y-3">
                <p class="section-title">Terbitkan Purchase Order</p>
                <p class="text-muted text-sm">
                    Hanya sisa yang belum dipesan yang dibawa ke PO, sehingga satu permintaan bisa dipecah
                    ke beberapa supplier.
                </p>

                <form method="POST" action="{{ route('purchase.orders.from-request', $request) }}"
                      class="flex flex-wrap items-end gap-3">
                    @csrf

                    <div>
                        <label class="label" for="supplier_id">Supplier</label>
                        <select id="supplier_id" name="supplier_id" class="input w-auto" required>
                            <option value="">Pilih supplier</option>
                            @foreach ($suppliers as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="btn-primary">
                        <x-icon name="cart" class="h-4 w-4" /> Buat PO
                    </button>
                </form>
            </div>
        @endcan
    @endif

    @include('purchase.partials.approval-trail', ['approval' => $approval])

    @if ($request->orders->isNotEmpty())
        <div class="card card-pad mt-4 space-y-3">
            <p class="section-title">Purchase Order Terkait</p>

            <div class="oasse-table-wrap">
                <table class="table-oasse">
                    <thead>
                        <tr>
                            <th>Nomor</th>
                            <th>Supplier</th>
                            <th>Tanggal</th>
                            <th class="text-right">Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($request->orders as $order)
                            <tr>
                                <td>
                                    <a href="{{ route('purchase.orders.detail', $order) }}" wire:navigate
                                       class="font-mono text-xs hover:text-brand-600">{{ $order->number }}</a>
                                </td>
                                <td>{{ $order->supplier?->name }}</td>
                                <td>{{ $order->order_date->format('d/m/Y') }}</td>
                                <td class="text-right">{{ Money::rupiah($order->total) }}</td>
                                <td><x-status-badge :status="$order->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($request->status === DocumentStatus::Submitted)
        @can('purchase.approve')
            <div class="card card-pad mt-4 space-y-3">
                <p class="section-title">Tolak Permintaan</p>

                <form method="POST" action="{{ route('purchase.requests.reject', $request) }}"
                      class="flex flex-wrap items-end gap-3">
                    @csrf

                    <div class="min-w-64 flex-1">
                        <label class="label" for="reason">Alasan</label>
                        <input id="reason" name="reason" class="input" required
                               placeholder="Contoh: kebutuhan bisa ditunda bulan depan">
                    </div>

                    <button type="submit" class="btn-danger">
                        <x-icon name="x" class="h-4 w-4" /> Tolak
                    </button>
                </form>
            </div>
        @endcan
    @endif

    <div class="card card-pad mt-4 space-y-2">
        <p class="section-title">Jejak Dokumen</p>

        <dl class="divide-hairline divide-y text-sm">
            @foreach ([
                'Diajukan' => $request->submitted_at?->format('d/m/Y H:i'),
                'Disetujui' => $request->approved_at?->format('d/m/Y H:i'),
                'Penyetuju' => $request->approver?->name,
            ] as $label => $value)
                <div class="flex items-start justify-between gap-3 py-2">
                    <dt class="text-muted">{{ $label }}</dt>
                    <dd class="text-right font-medium">{{ $value ?: '-' }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</x-layouts.app>
