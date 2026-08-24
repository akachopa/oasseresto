<?php

declare(strict_types=1);

namespace App\Modules\Core\Scopes;

use App\Modules\Core\Services\ScopeManager;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query object helper untuk membatasi hasil query ke branch/warehouse yang
 * boleh dilihat user (PLAN 4 dan 10). Dipakai eksplisit oleh Query Object
 * setiap modul, bukan sebagai global scope, supaya laporan lintas cabang
 * tetap mungkin bagi user dengan scope company.
 */
class LocationScope
{
    public function __construct(private readonly ScopeManager $manager) {}

    /**
     * Master data seperti customer boleh tidak terikat cabang, sehingga
     * baris ber-branch null tetap ikut terlihat bila $includeNull aktif.
     */
    public function applyBranch(Builder $query, string $column = 'branch_id', bool $includeNull = false): Builder
    {
        $branchIds = $this->manager->branchIds();

        if ($branchIds === null) {
            return $query;
        }

        $qualified = $query->qualifyColumn($column);

        return $query->where(function (Builder $builder) use ($qualified, $branchIds, $includeNull): void {
            $builder->whereIn($qualified, $branchIds);

            if ($includeNull) {
                $builder->orWhereNull($qualified);
            }
        });
    }

    public function applyWarehouse(Builder $query, string $column = 'warehouse_id'): Builder
    {
        $warehouseIds = $this->manager->warehouseIds();

        if ($warehouseIds === null) {
            return $query;
        }

        return $query->whereIn($query->qualifyColumn($column), $warehouseIds);
    }
}
