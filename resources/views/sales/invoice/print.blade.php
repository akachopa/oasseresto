@php
    use App\Modules\Core\Support\Money;
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white p-8 text-sm text-black">
    <div class="flex items-start justify-between gap-6">
        <div>
            <p class="text-lg font-semibold">{{ $invoice->company?->name }}</p>
            <p class="text-xs">{{ $invoice->branch?->name }}</p>
            <p class="text-xs">{{ $invoice->company?->address }}</p>
        </div>

        <div class="text-right">
            <p class="text-lg font-semibold">INVOICE</p>
            <p class="font-mono text-xs">{{ $invoice->number }}</p>
            <p class="text-xs">Tanggal {{ $invoice->invoice_date->format('d/m/Y') }}</p>
            <p class="text-xs">Jatuh tempo {{ $invoice->due_date->format('d/m/Y') }}</p>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-2 gap-6">
        <div>
            <p class="text-xs font-semibold uppercase">Tagihan Kepada</p>
            <p>{{ $invoice->customer?->name }}</p>
            <p class="text-xs">{{ $invoice->customer?->address }}</p>
            <p class="text-xs">{{ $invoice->customer?->phone }}</p>
        </div>

        <div>
            <p class="text-xs font-semibold uppercase">Keterangan</p>
            <p class="text-xs">Termin: {{ $invoice->payment_term->label() }}</p>
            <p class="text-xs">Surat jalan: {{ $invoice->delivery?->number ?: '-' }}</p>
        </div>
    </div>

    <table class="mt-6 w-full border-collapse text-xs">
        <thead>
            <tr class="border-b border-black">
                <th class="py-2 text-left">Produk</th>
                <th class="py-2 text-right">Qty</th>
                <th class="py-2 text-right">Harga</th>
                <th class="py-2 text-right">Diskon</th>
                <th class="py-2 text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr class="border-b border-gray-300">
                    <td class="py-2">
                        {{ $item->product?->name }}
                        <span class="block font-mono text-[10px]">{{ $item->product?->sku }}</span>
                    </td>
                    <td class="py-2 text-right">
                        {{ Money::quantity($item->quantity) }} {{ $item->unit?->code }}
                    </td>
                    <td class="py-2 text-right">{{ Money::rupiah($item->unit_price) }}</td>
                    <td class="py-2 text-right">{{ Money::rupiah($item->discount_amount) }}</td>
                    <td class="py-2 text-right">{{ Money::rupiah($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4 flex justify-end">
        <table class="text-xs">
            <tr>
                <td class="py-1 pr-6">Subtotal</td>
                <td class="py-1 text-right">{{ Money::rupiah($invoice->subtotal) }}</td>
            </tr>
            <tr>
                <td class="py-1 pr-6">Pajak</td>
                <td class="py-1 text-right">{{ Money::rupiah($invoice->tax_amount) }}</td>
            </tr>
            <tr>
                <td class="py-1 pr-6">Biaya Kirim</td>
                <td class="py-1 text-right">{{ Money::rupiah($invoice->shipping_cost) }}</td>
            </tr>
            <tr class="border-t border-black font-semibold">
                <td class="py-1 pr-6">Total</td>
                <td class="py-1 text-right">{{ Money::rupiah($invoice->total) }}</td>
            </tr>
            <tr>
                <td class="py-1 pr-6">Dibayar</td>
                <td class="py-1 text-right">{{ Money::rupiah($invoice->paid_amount) }}</td>
            </tr>
            <tr class="font-semibold">
                <td class="py-1 pr-6">Sisa</td>
                <td class="py-1 text-right">{{ Money::rupiah($invoice->outstanding_amount) }}</td>
            </tr>
        </table>
    </div>

    @if ($invoice->note)
        <p class="mt-6 text-xs">Catatan: {{ $invoice->note }}</p>
    @endif

    <div class="mt-12 grid grid-cols-2 gap-6 text-xs">
        <div>
            <p>Diterima oleh</p>
            <p class="mt-12 border-t border-black pt-1">{{ $invoice->customer?->name }}</p>
        </div>
        <div>
            <p>Hormat kami</p>
            <p class="mt-12 border-t border-black pt-1">{{ $invoice->company?->name }}</p>
        </div>
    </div>

    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
