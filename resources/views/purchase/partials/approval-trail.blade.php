@if ($approval)
    <div class="card card-pad mt-4 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="section-title">Jalur Approval</p>
            <x-status-badge :status="$approval->status" />
        </div>

        <ol class="divide-hairline divide-y text-sm">
            @foreach ($approval->steps as $step)
                <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <div class="min-w-0">
                        <p class="font-medium">
                            Langkah {{ $step->sequence }} · {{ $step->approverLabel() }}
                        </p>

                        @if ($step->note)
                            <p class="text-muted text-xs">{{ $step->note }}</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($step->acted_at)
                            <span class="text-muted text-xs">
                                {{ $step->actor?->name }} · {{ $step->acted_at->format('d/m/Y H:i') }}
                            </span>
                        @elseif ($step->sequence === $approval->current_step && $approval->isPending())
                            <span class="text-muted text-xs">menunggu keputusan</span>
                        @endif

                        <x-status-badge :status="$step->status" />
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
@endif
