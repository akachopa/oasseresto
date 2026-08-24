<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Company\Models\Company;
use App\Modules\Finance\Models\CashAccount;
use RuntimeException;

class AccountResolver
{
    /** @var array<int, array<string, Account>> */
    private array $cache = [];

    public function bySlug(Company $company, string $slug): Account
    {
        $this->warm($company);

        if (! isset($this->cache[$company->id][$slug])) {
            throw new RuntimeException("Akun dengan slug '{$slug}' belum terdaftar untuk perusahaan ini.");
        }

        return $this->cache[$company->id][$slug];
    }

    public function id(Company $company, string $slug): int
    {
        return (int) $this->bySlug($company, $slug)->getKey();
    }

    public function cashLedger(Company $company, CashAccount $cash): Account
    {
        if ($cash->account_id) {
            $account = Account::query()->find($cash->account_id);

            if ($account !== null) {
                return $account;
            }
        }

        return $this->bySlug($company, $cash->type === 'bank' ? 'bank' : 'cash');
    }

    private function warm(Company $company): void
    {
        if (isset($this->cache[$company->id])) {
            return;
        }

        $this->cache[$company->id] = Account::query()
            ->where('company_id', $company->id)
            ->whereNotNull('slug')
            ->get()
            ->keyBy('slug')
            ->all();
    }
}
