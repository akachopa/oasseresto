@php
    use App\Modules\Core\Support\Money;

    $profile = $customer->creditProfile;
    $available = $customer->availableCredit();
@endphp

<x-layouts.app :title="$customer->name">
    <x-page-header :title="$customer->name" :subtitle="$customer->code.' · '.$customer->group?->name"
                   :back="route('customers.index')">
        <x-slot:actions>
            @can('customer.edit')
                <a href="{{ route('customers.edit', $customer) }}" wire:navigate class="btn-secondary">
                    <x-icon name="pencil" class="h-4 w-4" /> Ubah
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Credit Limit" :value="Money::compact($customer->credit_limit)"
               :hint="$customer->effectivePaymentTerm()->label()" />

        <x-kpi label="Piutang Berjalan" :value="Money::compact($customer->outstanding_amount)"
               :tone="$customer->outstanding_amount > 0 ? 'caution' : 'neutral'" />

        <x-kpi label="Sisa Plafon" :value="Money::compact($available)"
               :tone="$available < 0 ? 'negative' : 'positive'" />

        <x-kpi label="Jatuh Tempo" :value="Money::compact($customer->overdue_amount)"
               :tone="$customer->overdue_amount > 0 ? 'negative' : 'neutral'"
               :hint="$profile ? $profile->overdue_count.' invoice' : null" />
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="card card-pad space-y-3">
            <p class="section-title">Kontak</p>

            <dl class="divide-hairline divide-y text-sm">
                @foreach ([
                    'PIC' => $customer->pic_name,
                    'Telepon' => $customer->phone,
                    'WhatsApp' => $customer->whatsapp,
                    'Email' => $customer->email,
                    'Kota' => $customer->city,
                    'Area Sales' => $customer->sales_area,
                    'Salesman' => $customer->salesman?->name,
                    'Cabang' => $customer->branch?->name ?? 'Semua cabang',
                    'Level Harga' => $customer->priceLevel?->name ?? $customer->group?->priceLevel?->name,
                    'NPWP' => $customer->tax_number,
                ] as $label => $value)
                    <div class="flex items-start justify-between gap-3 py-2">
                        <dt class="text-muted">{{ $label }}</dt>
                        <dd class="text-right font-medium">{{ $value ?: '-' }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($customer->address)
                <p class="text-muted border-hairline border-t pt-3 text-sm">{{ $customer->address }}</p>
            @endif
        </div>

        <div class="space-y-4 lg:col-span-2">
            <div class="card card-pad space-y-3">
                <p class="section-title">Perilaku Pembayaran</p>

                @if ($profile)
                    <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                        @foreach ([
                            'Total Pembelian' => Money::compact($profile->total_purchase),
                            'Jumlah Order' => (string) $profile->order_count,
                            'Rata-rata Invoice' => Money::compact($profile->average_invoice),
                            'Rata-rata Bayar' => number_format($profile->average_days_to_pay, 1, ',', '.').' hari',
                            'Order Terakhir' => $profile->last_order_date?->format('d/m/Y') ?? '-',
                            'Bayar Terakhir' => $profile->last_payment_date?->format('d/m/Y') ?? '-',
                        ] as $label => $value)
                            <div class="border-hairline rounded-lg border p-3">
                                <p class="text-muted text-xs">{{ $label }}</p>
                                <p class="mt-0.5 font-semibold tabular-nums">{{ $value }}</p>
                            </div>
                        @endforeach
                    </div>

                    <p class="text-muted text-sm">
                        Kategori pembayaran:
                        <span class="badge-{{ match ($profile->payment_behavior) {
                            'good' => 'success',
                            'slow' => 'warning',
                            'bad' => 'danger',
                            default => 'muted',
                        } }}">{{ match ($profile->payment_behavior) {
                            'good' => 'Tepat waktu',
                            'slow' => 'Sering terlambat',
                            'bad' => 'Bermasalah',
                            default => 'Belum ada riwayat',
                        } }}</span>
                    </p>
                @else
                    <p class="text-muted text-sm">Belum ada riwayat transaksi.</p>
                @endif
            </div>

            <div class="card card-pad space-y-3">
                <p class="section-title">Alamat Kirim</p>

                <div class="oasse-table-wrap">
                    <table class="table-oasse">
                        <thead>
                            <tr>
                                <th>Nama Alamat</th>
                                <th>Alamat</th>
                                <th>Kota</th>
                                <th>Kontak</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($customer->addresses as $address)
                                <tr>
                                    <td class="font-medium">
                                        {{ $address->label }}
                                        @if ($address->is_default)
                                            <span class="badge-info ml-1">Utama</span>
                                        @endif
                                    </td>
                                    <td>{{ $address->address }}</td>
                                    <td>{{ $address->city ?: '-' }}</td>
                                    <td class="text-muted text-xs">{{ collect([$address->pic_name, $address->phone])->filter()->join(' · ') ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">Memakai alamat utama customer.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($customer->note)
                <div class="card card-pad">
                    <p class="section-title mb-2">Catatan</p>
                    <p class="text-sm">{{ $customer->note }}</p>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
