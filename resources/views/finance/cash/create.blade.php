<x-layouts.app title="Tambah Akun Kas/Bank">
    <x-page-header title="Tambah Akun Kas/Bank"
                   subtitle="Saldo awal dicatat sebagai mutasi pertama supaya buku kas lengkap sejak baris pertama."
                   :back="route('finance.cash.index')" />

    <form method="POST" action="{{ route('finance.cash.store') }}" class="card card-pad space-y-4">
        @csrf

        @include('finance.cash.fields', ['account' => null])

        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="btn-primary">Simpan Akun</button>
            <a href="{{ route('finance.cash.index') }}" wire:navigate class="btn-ghost">Batal</a>
        </div>
    </form>
</x-layouts.app>
