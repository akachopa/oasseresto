@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Detail Approval">
    <x-page-header title="{{ $request->title }}"
                   :subtitle="$request->document_number"
                   :back="route('approval.requests.index')" />

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card card-pad space-y-2 lg:col-span-2">
            <p class="section-title">Langkah</p>

            @foreach ($request->steps as $step)
                <div class="border-hairline flex items-center justify-between border-b py-2 text-sm last:border-0">
                    <span>{{ $step->sequence }}. {{ $step->approverLabel() }}</span>
                    <x-status-badge :status="$step->status" />
                </div>
            @endforeach
        </div>

        <div class="card card-pad space-y-2">
            <p class="section-title">Ringkasan</p>
            <p class="text-sm">Nilai {{ Money::rupiah($request->amount) }}</p>
            <p class="text-sm">Pemohon {{ $request->requester?->name ?? '-' }}</p>
            <p class="text-sm">{{ $request->requested_at?->format('d/m/Y H:i') }}</p>
            <x-status-badge :status="$request->status" />

            @if ($canAct)
                <form method="POST" action="{{ route('approval.requests.approve', $request) }}" class="space-y-2 pt-2">
                    @csrf
                    <textarea name="note" class="input" rows="2" placeholder="Catatan"></textarea>
                    <div class="flex gap-2">
                        <button class="btn-primary" type="submit">Setujui</button>
                        <button class="btn-danger" type="submit"
                                formaction="{{ route('approval.requests.reject', $request) }}">Tolak</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
