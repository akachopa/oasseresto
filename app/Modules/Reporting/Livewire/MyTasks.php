<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Livewire;

use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Services\ApprovalService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use RuntimeException;

/**
 * Antrian tugas approval milik user yang sedang masuk (PLAN 46).
 */
class MyTasks extends Component
{
    public ?int $actingId = null;

    public string $note = '';

    public function start(int $id): void
    {
        $this->actingId = $id;
        $this->note = '';
    }

    public function cancelAct(): void
    {
        $this->actingId = null;
        $this->note = '';
    }

    public function approve(int $id): void
    {
        $this->act($id, true);
    }

    public function reject(int $id): void
    {
        $this->act($id, false);
    }

    public function render()
    {
        $user = Auth::user();

        return view('reporting.livewire.my-tasks', [
            'tasks' => app(ApprovalService::class)->pendingFor($user),
            'canAct' => $user->can('approval.act'),
        ]);
    }

    private function act(int $id, bool $approve): void
    {
        abort_unless(Auth::user()?->can('approval.act'), 403);

        $request = ApprovalRequest::query()->findOrFail($id);
        $service = app(ApprovalService::class);
        $user = Auth::user();

        try {
            if ($approve) {
                $service->approve($request, $user, $this->note !== '' ? $this->note : null);
                session()->flash('status', 'Dokumen disetujui.');
            } else {
                $service->reject($request, $user, $this->note !== '' ? $this->note : 'Ditolak');
                session()->flash('status', 'Dokumen ditolak.');
            }
        } catch (RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->cancelAct();
    }
}
