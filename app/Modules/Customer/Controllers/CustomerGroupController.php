<?php

declare(strict_types=1);

namespace App\Modules\Customer\Controllers;

use App\Modules\Core\Controllers\SimpleCrudController;
use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Support\Money;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerGroup;
use App\Modules\Product\Models\PriceLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerGroupController extends SimpleCrudController
{
    protected function model(): string
    {
        return CustomerGroup::class;
    }

    protected function routeName(): string
    {
        return 'customer-groups';
    }

    protected function viewPath(): string
    {
        return 'customer.group';
    }

    protected function title(): string
    {
        return 'Grup Customer';
    }

    protected function searchable(): array
    {
        return ['code', 'name'];
    }

    protected function orderable(): array
    {
        return [null, 'code', 'name', null, 'default_payment_term', 'default_credit_limit', null, null];
    }

    protected function formData(): array
    {
        return [
            'priceLevels' => PriceLevel::where('is_active', true)->orderBy('sequence')->pluck('name', 'id'),
            'paymentTerms' => PaymentTermType::options(),
        ];
    }

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('customer_groups', 'code')
                    ->where('company_id', auth()->user()->company_id)
                    ->ignore($record?->getKey()),
            ],
            'name' => ['required', 'string', 'max:255'],
            'price_level_id' => ['nullable', 'integer', 'exists:price_levels,id'],
            'default_payment_term' => ['required', 'string', 'in:'.implode(',', array_column(PaymentTermType::cases(), 'value'))],
            'default_credit_limit' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function row(Model $record): array
    {
        return [
            'code' => e($record->code),
            'name' => e($record->name),
            'price_level' => e((string) $record->priceLevel?->name),
            'payment_term' => e($record->default_payment_term->label()),
            'credit_limit' => Money::rupiah($record->default_credit_limit),
            'customers' => (string) Customer::where('customer_group_id', $record->getKey())->count(),
            'status' => $record->is_active
                ? '<span class="badge-success">Aktif</span>'
                : '<span class="badge-muted">Nonaktif</span>',
        ];
    }

    protected function beforeDelete(Model $record): ?string
    {
        return Customer::where('customer_group_id', $record->getKey())->exists()
            ? 'Grup masih dipakai oleh customer.'
            : null;
    }
}
