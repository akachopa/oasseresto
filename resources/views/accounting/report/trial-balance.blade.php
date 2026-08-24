@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Neraca Saldo">
    <x-page-header title="Neraca Saldo"
                   :subtitle="$report['balanced'] ? 'Debit dan kredit seimbang.' : 'Neraca saldo belum seimbang.'" />

    @include('accounting.report.partials.date-range')

    <div class="card overflow-hidden">
        <table class="table-oasse">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Akun</th>
                    <th>Tipe</th>
                    <th class="text-right">Debit</th>
                    <th class="text-right">Kredit</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['rows'] as $row)
                    <tr>
                        <td class="font-mono text-xs">{{ $row->code }}</td>
                        <td>{{ $row->name }}</td>
                        <td>{{ $row->type->label() }}</td>
                        <td class="text-right">{{ $row->debit > 0 ? Money::rupiah($row->debit) : '-' }}</td>
                        <td class="text-right">{{ $row->credit > 0 ? Money::rupiah($row->credit) : '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-muted py-6 text-center">Belum ada jurnal di periode ini.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="font-medium {{ $report['balanced'] ? '' : 'text-negative' }}">
                    <td colspan="3">Total</td>
                    <td class="text-right">{{ Money::rupiah($report['total_debit']) }}</td>
                    <td class="text-right">{{ Money::rupiah($report['total_credit']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</x-layouts.app>
