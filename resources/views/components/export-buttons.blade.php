@props([
    'type',
    'from' => null,
    'to' => null,
    'permission' => 'report.export',
])

@can($permission)
    @php
        $base = array_filter([
            'type' => $type,
            'from' => $from,
            'to' => $to,
        ], fn ($value) => $value !== null && $value !== '');
    @endphp
    <a href="{{ route('export.download', $base + ['format' => 'xlsx']) }}" class="btn-secondary">XLSX</a>
    <a href="{{ route('export.download', $base + ['format' => 'csv']) }}" class="btn-secondary">CSV</a>
    <a href="{{ route('export.download', $base + ['format' => 'pdf']) }}" class="btn-secondary">PDF</a>
@endcan
