<x-layouts.app title="Home Gudang">
    <x-page-header title="Home Gudang"
                   subtitle="Antrian penerimaan, picking, dan stok yang butuh perhatian hari ini." />

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <x-kpi label="Draft Penerimaan" :value="(string) $pending_receipts"
               :href="route('purchase.receipts.index')" icon="inbox" />
        <x-kpi label="Antrian Picking" :value="(string) $picking_queue"
               :href="route('delivery.picking.index')" icon="clipboard"
               :tone="$picking_queue > 0 ? 'caution' : 'positive'" />
        <x-kpi label="Di bawah Reorder" :value="(string) $below_reorder"
               :href="route('inventory.stock.index')" icon="boxes"
               :tone="$below_reorder > 0 ? 'caution' : 'positive'" />
        <x-kpi label="Mendekati Expired" :value="(string) $near_expiry"
               :href="route('inventory.batches.index')"
               :tone="$near_expiry > 0 ? 'caution' : 'positive'" />
        <x-kpi label="In Transit" :value="(string) $in_transit"
               :href="route('inventory.transfers.index')" />
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <div class="card card-pad">
            <p class="section-title mb-3">Penerimaan draft</p>
            @forelse ($receipts as $receipt)
                <a href="{{ route('purchase.receipts.detail', $receipt) }}" wire:navigate
                   class="border-hairline flex items-center justify-between border-b py-2 text-sm last:border-0">
                    <span class="font-mono text-xs">{{ $receipt->number }}</span>
                    <span class="text-muted truncate">{{ $receipt->supplier?->name }}</span>
                </a>
            @empty
                <p class="text-muted text-sm">Tidak ada draft penerimaan.</p>
            @endforelse
        </div>

        <div class="card card-pad">
            <p class="section-title mb-3">Antrian picking</p>
            @forelse ($deliveries as $delivery)
                <a href="{{ route('delivery.picking.pick', $delivery) }}" wire:navigate
                   class="border-hairline flex items-center justify-between border-b py-2 text-sm last:border-0">
                    <span class="font-mono text-xs">{{ $delivery->number }}</span>
                    <span class="text-muted truncate">{{ $delivery->customer?->name }}</span>
                </a>
            @empty
                <p class="text-muted text-sm">Tidak ada surat jalan yang menunggu picking.</p>
            @endforelse
        </div>
    </div>
</x-layouts.app>
