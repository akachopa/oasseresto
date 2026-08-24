<x-layouts.app :title="'Ubah '.$product->name">
    <x-page-header :title="'Ubah '.$product->name" :subtitle="$product->sku" :back="route('products.index')" />

    @livewire('product.product-form', ['product' => $product])
</x-layouts.app>
