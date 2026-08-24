<x-layouts.app title="Hasil Import">
    <x-page-header title="Import {{ $job->typeLabel() }}"
                   :subtitle="$job->original_filename"
                   :back="route('settings.import.index')" />

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Baris" :value="(string) $job->total_rows" />
        <x-kpi label="Baru" :value="(string) $job->created_count" tone="positive" />
        <x-kpi label="Diubah" :value="(string) $job->updated_count" />
        <x-kpi label="Error" :value="(string) $job->error_count"
               :tone="$job->error_count > 0 ? 'negative' : 'positive'" />
    </div>

    <p class="text-muted mt-3 text-sm">Status: {{ $job->statusLabel() }}</p>

    @if ($job->errors)
        <div class="card card-pad mt-4">
            <p class="section-title mb-2">Error</p>
            <ul class="space-y-1 text-sm">
                @foreach ($job->errors as $error)
                    <li>Baris {{ $error['row'] ?? '-' }}: {{ $error['message'] ?? '' }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</x-layouts.app>
