<x-layouts.app :title="'Ubah '.$account->name">
    <x-page-header :title="'Ubah '.$account->name"
                   subtitle="Perubahan di sini tidak mengubah mutasi yang sudah tercatat."
                   :back="route('finance.cash.detail', $account)" />

    <form method="POST" action="{{ route('finance.cash.update', $account) }}" class="card card-pad space-y-4">
        @csrf
        @method('PUT')

        @include('finance.cash.fields')

        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="btn-primary">Simpan Perubahan</button>
            <a href="{{ route('finance.cash.index') }}" wire:navigate class="btn-ghost">Batal</a>
        </div>
    </form>
</x-layouts.app>
