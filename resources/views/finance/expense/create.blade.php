<x-layouts.app title="Input Biaya">
    <x-page-header title="Input Biaya"
                   subtitle="Biaya kecil di bawah ambang kategori langsung disetujui agar operasional tidak terhambat."
                   :back="route('finance.expenses.index')" />

    <form method="POST" action="{{ route('finance.expenses.store') }}" class="card card-pad space-y-4">
        @csrf

        @include('finance.expense.fields', ['expense' => null])

        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="btn-secondary">Simpan Draft</button>
            <button type="submit" name="submit" value="1" class="btn-primary">Simpan & Ajukan</button>
            <a href="{{ route('finance.expenses.index') }}" wire:navigate class="btn-ghost">Batal</a>
        </div>
    </form>
</x-layouts.app>
