<x-layouts.app :title="'Ubah '.$request->number">
    <x-page-header :title="'Ubah '.$request->number"
                   subtitle="Purchase request hanya bisa diubah selama masih draft."
                   :back="route('purchase.requests.detail', $request)" />

    @livewire('purchase.request-form', ['request' => $request])
</x-layouts.app>
