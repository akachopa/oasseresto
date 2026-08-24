<?php

declare(strict_types=1);

namespace App\Modules\Approval\Contracts;

use App\Modules\Approval\Models\ApprovalRequest;

/**
 * Dokumen yang bisa dimintakan persetujuan. Engine approval tidak tahu isi
 * dokumen: ia hanya butuh identitas, nilai yang dinilai, dan apa yang harus
 * terjadi setelah keputusan diambil (PLAN 44).
 */
interface Approvable
{
    public function approvalDocumentType(): string;

    public function approvalTitle(): string;

    public function approvalAmount(): float;

    public function approvalBranchId(): ?int;

    public function approvalUrl(): ?string;

    public function onApprovalApproved(ApprovalRequest $request): void;

    public function onApprovalRejected(ApprovalRequest $request): void;
}
