<x-layouts.app :title="'Ubah '.$return->number">
    <x-page-header :title="'Ubah '.$return->number"
                   subtitle="Retur hanya bisa diubah selama belum diposting."
                   :back="route('purchase.returns.detail', $return)" />

    @livewire('purchase.return-form', ['return' => $return])
</x-layouts.app>
