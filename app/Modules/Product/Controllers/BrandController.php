<?php

declare(strict_types=1);

namespace App\Modules\Product\Controllers;

use App\Modules\Core\Controllers\SimpleCrudController;
use App\Modules\Product\Models\Brand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BrandController extends SimpleCrudController
{
    protected function model(): string
    {
        return Brand::class;
    }

    protected function routeName(): string
    {
        return 'brands';
    }

    protected function viewPath(): string
    {
        return 'product.brand';
    }

    protected function title(): string
    {
        return 'Brand';
    }

    protected function searchable(): array
    {
        return ['code', 'name'];
    }

    protected function orderable(): array
    {
        return [null, 'code', 'name', null, null];
    }

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('brands', 'code')
                    ->where('company_id', auth()->user()->company_id)
                    ->ignore($record?->getKey()),
            ],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    protected function row(Model $record): array
    {
        return [
            'code' => e($record->code),
            'name' => e($record->name),
            'products' => $record->products()->count().' produk',
            'status' => $record->is_active
                ? '<span class="badge-success">Aktif</span>'
                : '<span class="badge-muted">Nonaktif</span>',
        ];
    }

    protected function beforeDelete(Model $record): ?string
    {
        return $record->products()->exists() ? 'Brand masih dipakai oleh produk.' : null;
    }
}
