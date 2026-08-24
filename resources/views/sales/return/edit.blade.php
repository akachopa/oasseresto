<x-layouts.app :title="'Ubah '.$return->number">
    <x-page-header :title="'Ubah '.$return->number"
                   subtitle="Retur hanya bisa diubah selama belum diposting."
                   :back="route('sales.returns.detail', $return)" />

    @livewire('sales.return-form', ['return' => $return])
</x-layouts.app>
