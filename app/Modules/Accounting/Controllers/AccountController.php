<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Controllers;

use App\Modules\Accounting\Models\Account;
use App\Modules\Core\Controllers\SimpleCrudController;
use App\Modules\Core\Enums\AccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends SimpleCrudController
{
    protected function model(): string
    {
        return Account::class;
    }

    protected function routeName(): string
    {
        return 'accounting.accounts';
    }

    protected function viewPath(): string
    {
        return 'accounting.account';
    }

    protected function title(): string
    {
        return 'Chart of Accounts';
    }

    protected function searchable(): array
    {
        return ['code', 'name', 'slug'];
    }

    protected function orderable(): array
    {
        return [null, 'code', 'name', 'type', null, null, null];
    }

    protected function formData(): array
    {
        return [
            'types' => collect(AccountType::cases())->mapWithKeys(fn (AccountType $type) => [$type->value => $type->label()]),
            'parents' => Account::query()->where('is_postable', false)->orderBy('code')->get()->mapWithKeys(
                fn (Account $account) => [$account->id => $account->label]
            ),
        ];
    }

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'parent_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('accounts', 'code')
                    ->where('company_id', auth()->user()->company_id)
                    ->ignore($record?->getKey()),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'slug' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string'],
        ];
    }

    protected function booleans(Request $request): array
    {
        return [
            'is_active' => $request->boolean('is_active'),
            'is_postable' => $request->boolean('is_postable'),
        ];
    }

    protected function row(Model $record): array
    {
        /** @var Account $record */
        return [
            'code' => '<span class="font-mono text-xs">'.e($record->code).'</span>',
            'name' => e($record->name).($record->is_system ? ' <span class="badge-muted ml-1">sistem</span>' : ''),
            'type' => e($record->type->label()),
            'slug' => $record->slug ? '<span class="font-mono text-xs">'.e($record->slug).'</span>' : '<span class="text-muted">-</span>',
            'postable' => $record->is_postable ? '<span class="badge-success">Postable</span>' : '<span class="badge-muted">Header</span>',
            'status' => $record->is_active
                ? '<span class="badge-success">Aktif</span>'
                : '<span class="badge-muted">Nonaktif</span>',
        ];
    }

    protected function beforeDelete(Model $record): ?string
    {
        /** @var Account $record */
        if ($record->is_system) {
            return 'Akun sistem tidak bisa dihapus.';
        }

        if ($record->lines()->exists()) {
            return 'Akun yang sudah dipakai jurnal tidak bisa dihapus.';
        }

        if ($record->children()->exists()) {
            return 'Akun induk masih punya anak.';
        }

        return null;
    }
}
