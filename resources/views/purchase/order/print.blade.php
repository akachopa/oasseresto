@php
    use App\Modules\Core\Support\Money;
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $order->number }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white p-8 text-sm text-black">
    <div class="flex items-start justify-between gap-6">
        <div>
            <p class="text-lg font-semibold">{{ $order->company?->name }}</p>
            <p class="text-xs">{{ $order->branch?->name }}</p>
            <p class="text-xs">{{ $order->company?->address }}</p>
        </div>

        <div class="text-right">
            <p class="text-lg font-semibold">PURCHASE ORDER</p>
            <p class="font-mono text-xs">{{ $order->number }}</p>
            <p class="text-xs">Tanggal {{ $order->order_date->format('d/m/Y') }}</p>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-2 gap-6">
        <div>
            <p class="text-xs font-semibold uppercase">Supplier</p>
            <p>{{ $order->supplier?->name }}</p>
            <p class="text-xs">{{ $order->supplier?->address }}</p>
            <p class="text-xs">{{ $order->supplier?->phone }}</p>
        </div>

        <div>
            <p class="text-xs font-semibold uppercase">Kirim Ke</p>
            <p>{{ $order->warehouse?->name }}</p>
            <p class="text-xs">{{ $order->warehouse?->address }}</p>
            <p class="text-xs">
                Termin {{ $order->payment_term->label() }} ·
                perkiraan datang {{ $order->expected_date?->format('d/m/Y') ?? '-' }}
            </p>
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
            @foreach ($order->items as $item)
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
                <td class="py-1 text-right">{{ Money::rupiah($order->subtotal) }}</td>
            </tr>
            <tr>
                <td class="py-1 pr-6">Pajak</td>
                <td class="py-1 text-right">{{ Money::rupiah($order->tax_amount) }}</td>
            </tr>
            <tr>
                <td class="py-1 pr-6">Biaya Lain</td>
                <td class="py-1 text-right">{{ Money::rupiah($order->other_cost) }}</td>
            </tr>
            <tr class="border-t border-black font-semibold">
                <td class="py-1 pr-6">Total</td>
                <td class="py-1 text-right">{{ Money::rupiah($order->total) }}</td>
            </tr>
        </table>
    </div>

    @if ($order->terms)
        <p class="mt-6 text-xs">Syarat: {{ $order->terms }}</p>
    @endif

    <div class="mt-12 grid grid-cols-2 gap-6 text-xs">
        <div>
            <p>Dibuat oleh</p>
            <p class="mt-12 border-t border-black pt-1">{{ $order->creator?->name }}</p>
        </div>
        <div>
            <p>Disetujui oleh</p>
            <p class="mt-12 border-t border-black pt-1">{{ $order->approver?->name ?? '' }}</p>
        </div>
    </div>

    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
