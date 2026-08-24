<x-layouts.app title="Import Data">
    <x-page-header title="Import Data"
                   subtitle="Unggah CSV atau XLSX. Proses berjalan di antrian supaya tidak memblokir layar." />

    <form method="POST" action="{{ route('settings.import.store') }}" enctype="multipart/form-data"
          class="card card-pad mb-4 max-w-xl space-y-4">
        @csrf

        <div>
            <label class="label" for="type">Jenis data</label>
            <select id="type" name="type" class="input" required>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="label" for="file">Berkas</label>
            <input id="file" name="file" type="file" class="input" accept=".csv,.xlsx,.txt" required>
            @error('file') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-wrap gap-2">
            <button class="btn-primary" type="submit">Unggah & proses</button>
            <a href="{{ route('settings.import.template', 'products') }}" class="btn-secondary">Template produk</a>
            <a href="{{ route('settings.import.template', 'customers') }}" class="btn-secondary">Template customer</a>
            <a href="{{ route('settings.import.template', 'suppliers') }}" class="btn-secondary">Template supplier</a>
        </div>
    </form>

    <x-data-table
        id="tbl-imports"
        :url="route('settings.import.data')"
        :order="[[1, 'desc']]"
        :columns="[
            ['data' => 'created_at', 'title' => 'Waktu'],
            ['data' => 'type', 'title' => 'Jenis'],
            ['data' => 'file', 'title' => 'Berkas'],
            ['data' => 'result', 'title' => 'Hasil', 'orderable' => false],
            ['data' => 'status', 'title' => 'Status'],
        ]" />
</x-layouts.app>
