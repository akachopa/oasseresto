@php
    use App\Modules\Core\Support\Money;
@endphp

<div class="space-y-3">
    @forelse ($tasks as $task)
        <div class="card card-pad">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-medium">{{ $task->title }}</p>
                    <p class="text-muted mt-0.5 text-sm">
                        {{ $task->document_number }}
                        · {{ Money::rupiah($task->amount) }}
                        · diajukan {{ $task->requested_at?->format('d/m/Y H:i') }}
                        @if ($task->requester)
                            oleh {{ $task->requester->name }}
                        @endif
                    </p>
                    @if ($task->reason)
                        <p class="text-muted mt-1 text-xs">{{ $task->reason }}</p>
                    @endif
                </div>

                <x-status-badge :status="$task->status" />
            </div>

            @if ($canAct)
                @if ($actingId === $task->id)
                    <div class="mt-3 space-y-2">
                        <textarea wire:model="note" rows="2" class="input" placeholder="Catatan (opsional)"></textarea>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" wire:click="approve({{ $task->id }})" class="btn-primary">Setujui</button>
                            <button type="button" wire:click="reject({{ $task->id }})" class="btn-danger">Tolak</button>
                            <button type="button" wire:click="cancelAct" class="btn-ghost">Batal</button>
                        </div>
                    </div>
                @else
                    <div class="mt-3 flex flex-wrap gap-2">
                        @php
                            $document = $task->document();
                            $url = $document instanceof \App\Modules\Approval\Contracts\Approvable
                                ? $document->approvalUrl()
                                : null;
                        @endphp
                        @if ($url)
                            <a href="{{ $url }}" wire:navigate class="btn-secondary">Lihat dokumen</a>
                        @endif
                        <button type="button" wire:click="start({{ $task->id }})" class="btn-primary">Putuskan</button>
                    </div>
                @endif
            @endif
        </div>
    @empty
        <x-empty-state title="Tidak ada tugas menunggu"
                       message="Approval yang membutuhkan keputusan Anda akan muncul di sini."
                       icon="check" />
    @endforelse
</div>
