<x-layouts.app title="Kasir">
    <x-page-header title="Kasir (POS)"
                   :subtitle="'Shift '.$shift->number.' · '.$shift->warehouse?->name">
        <x-slot:actions>
            <a href="{{ route('pos.shift') }}" wire:navigate class="btn-secondary">
                <x-icon name="clock" class="h-4 w-4" /> Kelola Shift
            </a>
        </x-slot:actions>
    </x-page-header>

    @livewire('sales.pos-terminal', ['shift' => $shift])
</x-layouts.app>
