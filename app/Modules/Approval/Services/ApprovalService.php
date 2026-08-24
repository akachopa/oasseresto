<?php

declare(strict_types=1);

namespace App\Modules\Approval\Services;

use App\Models\User;
use App\Modules\Approval\Contracts\Approvable;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Approval\Models\ApprovalStep;
use App\Modules\Core\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Engine approval generik (PLAN 44). Modul bisnis hanya memanggil request()
 * dan menunggu callback; siapa yang menyetujui dan berapa langkahnya
 * ditentukan oleh approval_rules, bukan oleh kode modul.
 */
class ApprovalService
{
    /**
     * Buat permintaan approval untuk sebuah dokumen. Mengembalikan null bila
     * tidak ada aturan yang cocok, artinya dokumen boleh langsung lanjut.
     *
     * @param  Approvable&Model  $document
     */
    public function request(
        Approvable $document,
        string $trigger = 'always',
        ?string $reason = null,
    ): ?ApprovalRequest {
        $amount = $document->approvalAmount();
        $branchId = $document->approvalBranchId();

        $rules = $this->rulesFor($document->approvalDocumentType(), $trigger, $amount, $branchId);

        if ($rules->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($document, $rules, $trigger, $reason, $amount, $branchId): ApprovalRequest {
            $this->cancelOpenRequests($document);

            $request = ApprovalRequest::create([
                'branch_id' => $branchId,
                'document_type' => $document->approvalDocumentType(),
                'document_id' => $document->getKey(),
                'document_number' => $document->getAttribute('number'),
                'title' => $document->approvalTitle(),
                'amount' => $amount,
                'status' => ApprovalStatus::Pending,
                'trigger' => $trigger,
                'reason' => $reason,
                'current_step' => 1,
                'requested_by' => Auth::id(),
                'requested_at' => now(),
            ]);

            foreach ($rules->values() as $index => $rule) {
                ApprovalStep::create([
                    'company_id' => $request->company_id,
                    'approval_request_id' => $request->getKey(),
                    'approval_rule_id' => $rule->getKey(),
                    'sequence' => $index + 1,
                    'approver_role' => $rule->approver_role,
                    'approver_user_id' => $rule->approver_user_id,
                    'status' => ApprovalStatus::Pending,
                ]);
            }

            return $request->load('steps');
        });
    }

    /**
     * Setujui langkah aktif. Dokumen baru dianggap disetujui setelah seluruh
     * langkah selesai, sehingga approval berjenjang tetap berurutan.
     */
    public function approve(ApprovalRequest $request, ?User $actor = null, ?string $note = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $actor, $note): ApprovalRequest {
            $step = $this->assertActionable($request, $actor);

            $step->status = ApprovalStatus::Approved;
            $step->acted_by = $actor?->getKey() ?? Auth::id();
            $step->acted_at = now();
            $step->note = $note;
            $step->save();

            $request->refresh()->load('steps');

            $next = $request->steps->firstWhere('status', ApprovalStatus::Pending);

            if ($next !== null) {
                $request->current_step = $next->sequence;
                $request->save();

                return $request;
            }

            $request->status = ApprovalStatus::Approved;
            $request->completed_at = now();
            $request->save();

            $this->document($request)?->onApprovalApproved($request);

            return $request;
        });
    }

    public function reject(ApprovalRequest $request, ?User $actor = null, ?string $note = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $actor, $note): ApprovalRequest {
            $step = $this->assertActionable($request, $actor);

            $step->status = ApprovalStatus::Rejected;
            $step->acted_by = $actor?->getKey() ?? Auth::id();
            $step->acted_at = now();
            $step->note = $note;
            $step->save();

            $request->status = ApprovalStatus::Rejected;
            $request->completed_at = now();
            $request->save();

            $this->document($request)?->onApprovalRejected($request);

            return $request;
        });
    }

    /**
     * @param  Approvable&Model  $document
     */
    public function cancelOpenRequests(Approvable $document): void
    {
        ApprovalRequest::where('document_type', $document->approvalDocumentType())
            ->where('document_id', $document->getKey())
            ->pending()
            ->get()
            ->each(function (ApprovalRequest $request): void {
                $request->status = ApprovalStatus::Cancelled;
                $request->completed_at = now();
                $request->save();

                $request->steps()->where('status', ApprovalStatus::Pending)
                    ->update(['status' => ApprovalStatus::Cancelled->value]);
            });
    }

    /**
     * Permintaan yang menunggu keputusan user ini, dipakai My Tasks (PLAN 46).
     *
     * @return Collection<int, ApprovalRequest>
     */
    public function pendingFor(User $user): Collection
    {
        $roles = $user->getRoleNames()->all();

        return ApprovalRequest::query()
            ->pending()
            ->whereHas('steps', fn ($query) => $query
                ->where('status', ApprovalStatus::Pending)
                ->whereColumn('approval_steps.sequence', 'approval_requests.current_step')
                ->where(fn ($sub) => $sub
                    ->where('approver_user_id', $user->getKey())
                    ->orWhereIn('approver_role', $roles)))
            ->with('steps', 'requester')
            ->latest('requested_at')
            ->get();
    }

    public function canAct(ApprovalRequest $request, User $user): bool
    {
        if (! $request->isPending()) {
            return false;
        }

        $step = $request->relationLoaded('steps')
            ? $request->currentStep()
            : $request->steps()->where('sequence', $request->current_step)->first();

        return $step !== null && $step->isFor($user);
    }

    /**
     * @return Collection<int, ApprovalRule>
     */
    private function rulesFor(string $documentType, string $trigger, float $amount, ?int $branchId): Collection
    {
        return ApprovalRule::query()
            ->active()
            ->where('document_type', $documentType)
            ->orderBy('sequence')
            ->get()
            ->filter(fn (ApprovalRule $rule) => $rule->matches($trigger, $amount, $branchId))
            ->values();
    }

    private function assertActionable(ApprovalRequest $request, ?User $actor): ApprovalStep
    {
        if (! $request->isPending()) {
            throw new RuntimeException('Permintaan approval ini sudah diputuskan.');
        }

        $step = $request->steps()->where('sequence', $request->current_step)->first();

        if ($step === null) {
            throw new RuntimeException('Langkah approval tidak ditemukan.');
        }

        $actor ??= Auth::user();

        /*
         * Pemegang approval.rule.manage (owner/admin) boleh menembus langkah
         * yang bukan miliknya supaya approval tidak macet saat penyetuju
         * yang ditunjuk tidak tersedia.
         */
        if ($actor instanceof User && ! $step->isFor($actor) && ! $actor->can('approval.rule.manage')) {
            throw new RuntimeException('Anda bukan penyetuju untuk langkah ini.');
        }

        return $step;
    }

    private function document(ApprovalRequest $request): ?Approvable
    {
        $document = $request->document();

        return $document instanceof Approvable ? $document : null;
    }
}
