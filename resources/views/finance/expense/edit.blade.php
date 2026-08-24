<x-layouts.app :title="'Ubah '.$expense->number">
    <x-page-header :title="'Ubah '.$expense->number"
                   subtitle="Biaya hanya bisa diubah selama belum diajukan."
                   :back="route('finance.expenses.detail', $expense)" />

    <form method="POST" action="{{ route('finance.expenses.update', $expense) }}" class="card card-pad space-y-4">
        @csrf
        @method('PUT')

        @include('finance.expense.fields')

        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="btn-secondary">Simpan Perubahan</button>
            <button type="submit" name="submit" value="1" class="btn-primary">Simpan & Ajukan</button>
            <a href="{{ route('finance.expenses.detail', $expense) }}" wire:navigate class="btn-ghost">Batal</a>
        </div>
    </form>
</x-layouts.app>
