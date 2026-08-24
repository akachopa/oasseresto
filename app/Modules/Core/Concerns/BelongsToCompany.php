<?php

declare(strict_types=1);

namespace App\Modules\Core\Concerns;

use App\Modules\Company\Models\Company;
use App\Modules\Core\Scopes\CompanyScope;
use App\Modules\Core\Services\ScopeManager;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Semua tabel bisnis wajib punya company_id (PLAN 4). Trait ini memasang
 * global scope company sekaligus mengisi company_id saat pembuatan record
 * sehingga tidak ada modul yang perlu mengingatnya secara manual.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model): void {
            if (! $model->getAttribute('company_id')) {
                $companyId = app(ScopeManager::class)->companyId();

                if ($companyId) {
                    $model->setAttribute('company_id', $companyId);
                }
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
