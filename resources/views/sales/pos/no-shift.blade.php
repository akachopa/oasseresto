<x-layouts.app title="Kasir">
    <x-page-header title="Kasir (POS)"
                   subtitle="Shift harus dibuka lebih dulu agar setiap rupiah di drawer bisa dipertanggungjawabkan." />

    <div class="card mx-auto max-w-lg">
        <x-empty-state icon="cash-register" title="Belum ada shift terbuka"
                       message="Masukkan modal awal kas, lalu buka shift untuk mulai berjualan." />

        @can('pos.shift.open')
            <form method="POST" action="{{ route('pos.shift.open') }}" class="card-pad space-y-3 border-t border-hairline">
                @csrf

                <div>
                    <label class="label" for="warehouse_id">Gudang Counter</label>
                    <select id="warehouse_id" name="warehouse_id" class="input" required>
                        <option value="">Pilih gudang</option>
                        @foreach ($warehouses as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label" for="opening_cash">Modal Awal Kas</label>
                    <input id="opening_cash" name="opening_cash" type="number" step="0.01" min="0" value="0"
                           class="input text-right">
                </div>

                <button type="submit" class="btn-primary w-full justify-center">
                    <x-icon name="cash-register" class="h-4 w-4" /> Buka Shift
                </button>
            </form>
        @endcan
    </div>
</x-layouts.app>
