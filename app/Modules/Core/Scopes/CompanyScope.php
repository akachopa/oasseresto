<?php

declare(strict_types=1);

namespace App\Modules\Core\Scopes;

use App\Modules\Core\Services\ScopeManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $manager = app(ScopeManager::class);

        if (! $manager->isEnforced()) {
            return;
        }

        $companyId = $manager->companyId();

        if ($companyId === null) {
            return;
        }

        $builder->where($model->qualifyColumn('company_id'), $companyId);
    }
}
