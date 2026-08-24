<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use App\Models\User;
use App\Modules\Auth\Models\UserScope;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\ScopeLevel;
use Illuminate\Support\Facades\Auth;

/**
 * Sumber tunggal jawaban atas pertanyaan "company/branch/warehouse mana yang
 * boleh diakses user saat ini" (PLAN 4 dan 10).
 */
class ScopeManager
{
    private ?int $companyId = null;

    private ?int $branchId = null;

    private bool $enforced = true;

    /** @var array<int, int>|null */
    private ?array $branchIds = null;

    /** @var array<int, int>|null */
    private ?array $warehouseIds = null;

    private bool $resolved = false;

    public function companyId(): ?int
    {
        $this->resolve();

        return $this->companyId;
    }

    public function company(): ?Company
    {
        $id = $this->companyId();

        return $id ? Company::withoutGlobalScopes()->find($id) : null;
    }

    /**
     * Branch aktif yang dipilih user (untuk penomoran dokumen dan default form).
     */
    public function branchId(): ?int
    {
        $this->resolve();

        if ($this->branchId !== null) {
            return $this->branchId;
        }

        $ids = $this->branchIds();

        return $ids !== null && count($ids) === 1 ? $ids[0] : null;
    }

    public function branch(): ?Branch
    {
        $id = $this->branchId();

        return $id ? Branch::withoutGlobalScopes()->find($id) : null;
    }

    /**
     * null berarti tidak dibatasi (scope company).
     *
     * @return array<int, int>|null
     */
    public function branchIds(): ?array
    {
        $this->resolve();

        return $this->branchIds;
    }

    /**
     * @return array<int, int>|null
     */
    public function warehouseIds(): ?array
    {
        $this->resolve();

        return $this->warehouseIds;
    }

    public function canAccessBranch(?int $branchId): bool
    {
        if ($branchId === null) {
            return true;
        }

        $ids = $this->branchIds();

        return $ids === null || in_array($branchId, $ids, true);
    }

    public function canAccessWarehouse(?int $warehouseId): bool
    {
        if ($warehouseId === null) {
            return true;
        }

        $ids = $this->warehouseIds();

        return $ids === null || in_array($warehouseId, $ids, true);
    }

    public function isEnforced(): bool
    {
        return $this->enforced;
    }

    public function setCompanyId(?int $companyId): self
    {
        $this->companyId = $companyId;
        $this->resolved = true;

        return $this;
    }

    public function setBranchId(?int $branchId): self
    {
        $this->branchId = $branchId;

        return $this;
    }

    /**
     * Jalankan callback tanpa pembatasan company. Dipakai oleh seeder,
     * scheduler, dan job yang memang harus lintas company.
     */
    public function withoutRestriction(callable $callback): mixed
    {
        $previous = $this->enforced;
        $this->enforced = false;

        try {
            return $callback();
        } finally {
            $this->enforced = $previous;
        }
    }

    /**
     * Jalankan callback dalam konteks company tertentu.
     */
    public function forCompany(int $companyId, callable $callback, ?int $branchId = null): mixed
    {
        $previousCompany = $this->companyId;
        $previousBranch = $this->branchId;
        $previousBranchIds = $this->branchIds;
        $previousWarehouseIds = $this->warehouseIds;
        $previousResolved = $this->resolved;

        $this->companyId = $companyId;
        $this->branchId = $branchId;
        $this->branchIds = null;
        $this->warehouseIds = null;
        $this->resolved = true;

        try {
            return $callback();
        } finally {
            $this->companyId = $previousCompany;
            $this->branchId = $previousBranch;
            $this->branchIds = $previousBranchIds;
            $this->warehouseIds = $previousWarehouseIds;
            $this->resolved = $previousResolved;
        }
    }

    public function reset(): void
    {
        $this->companyId = null;
        $this->branchId = null;
        $this->branchIds = null;
        $this->warehouseIds = null;
        $this->resolved = false;
        $this->enforced = true;
    }

    private function resolve(): void
    {
        if ($this->resolved) {
            return;
        }

        $this->resolved = true;

        $user = Auth::user();

        if (! $user instanceof User) {
            return;
        }

        $this->companyId = $user->company_id;
        $this->branchId = session('oasse.branch_id') ?: $user->default_branch_id;

        $scopes = UserScope::withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->get();

        if ($scopes->isEmpty() || $scopes->contains(fn (UserScope $s) => $s->level === ScopeLevel::Company)) {
            return;
        }

        $branchIds = $scopes
            ->where('level', ScopeLevel::Branch)
            ->pluck('branch_id')
            ->filter()
            ->all();

        $warehouseScopes = $scopes->where('level', ScopeLevel::Warehouse);

        if ($warehouseScopes->isNotEmpty()) {
            $this->warehouseIds = array_values(array_unique(
                $warehouseScopes->pluck('warehouse_id')->filter()->all()
            ));

            $branchIds = array_merge($branchIds, Warehouse::withoutGlobalScopes()
                ->whereIn('id', $this->warehouseIds)
                ->pluck('branch_id')
                ->all());
        }

        $this->branchIds = array_values(array_unique(array_filter($branchIds)));

        if ($this->warehouseIds === null && $this->branchIds !== []) {
            $this->warehouseIds = Warehouse::withoutGlobalScopes()
                ->whereIn('branch_id', $this->branchIds)
                ->pluck('id')
                ->all();
        }

        if ($this->branchIds === []) {
            $this->branchIds = null;
        }

        if ($this->branchId !== null && ! $this->canAccessBranch($this->branchId)) {
            $this->branchId = $this->branchIds[0] ?? null;
        }
    }
}
