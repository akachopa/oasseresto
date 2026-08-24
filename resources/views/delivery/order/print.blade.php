@php
    use App\Modules\Core\Support\Money;
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $delivery->number }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white p-8 text-sm text-black">
    <div class="flex items-start justify-between gap-6">
        <div>
            <p class="text-lg font-semibold">{{ $delivery->company?->name }}</p>
            <p class="text-xs">{{ $delivery->branch?->name }}</p>
            <p class="text-xs">{{ $delivery->warehouse?->name }}</p>
        </div>

        <div class="text-right">
            <p class="text-lg font-semibold">SURAT JALAN</p>
            <p class="font-mono text-xs">{{ $delivery->number }}</p>
            <p class="text-xs">Tanggal {{ $delivery->delivery_date->format('d/m/Y') }}</p>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-2 gap-6">
        <div>
            <p class="text-xs font-semibold uppercase">Kirim Ke</p>
            <p>{{ $delivery->customer?->name }}</p>
            <p class="text-xs">{{ $delivery->shipping_address ?: $delivery->customer?->address }}</p>
            <p class="text-xs">{{ $delivery->customer?->phone }}</p>
        </div>

        <div>
            <p class="text-xs font-semibold uppercase">Pengangkutan</p>
            <p class="text-xs">Driver: {{ $delivery->driver_name ?: '-' }}</p>
            <p class="text-xs">Kendaraan: {{ $delivery->vehicle_number ?: '-' }}</p>
            <p class="text-xs">Sales Order: {{ $delivery->order?->number ?: '-' }}</p>
        </div>
    </div>

    <table class="mt-6 w-full border-collapse text-xs">
        <thead>
            <tr class="border-b border-black">
                <th class="py-2 text-left">Produk</th>
                <th class="py-2 text-right">Qty Kirim</th>
                <th class="py-2 text-left">Satuan</th>
                <th class="py-2 text-left">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($delivery->items as $item)
                <tr class="border-b border-gray-300">
                    <td class="py-2">
                        {{ $item->product?->name }}
                        <span class="block font-mono text-[10px]">{{ $item->product?->sku }}</span>
                    </td>
                    <td class="py-2 text-right">{{ Money::quantity($item->picked_base_quantity) }}</td>
                    <td class="py-2">{{ $item->product?->baseUnit?->code }}</td>
                    <td class="py-2">{{ $item->note }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($delivery->note)
        <p class="mt-4 text-xs">Catatan: {{ $delivery->note }}</p>
    @endif

    <div class="mt-12 grid grid-cols-3 gap-6 text-xs">
        <div>
            <p>Petugas Gudang</p>
            <p class="mt-12 border-t border-black pt-1">{{ $delivery->picker?->name }}</p>
        </div>
        <div>
            <p>Driver</p>
            <p class="mt-12 border-t border-black pt-1">{{ $delivery->driver_name }}</p>
        </div>
        <div>
            <p>Penerima</p>
            <p class="mt-12 border-t border-black pt-1">{{ $delivery->recipient_name }}</p>
        </div>
    </div>

    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
