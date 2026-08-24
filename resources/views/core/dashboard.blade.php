<x-layouts.app title="Dashboard">
    @php $user = auth()->user(); @endphp

    <x-page-header :title="'Selamat datang, '.$user->name"
                   :subtitle="$user->company?->name.' - '.($user->roles->pluck('name')->join(', ') ?: 'Tanpa role')" />

    <livewire:reporting.owner-dashboard />
</x-layouts.app>
