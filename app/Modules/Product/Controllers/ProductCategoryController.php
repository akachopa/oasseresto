<?php

declare(strict_types=1);

namespace App\Modules\Product\Controllers;

use App\Modules\Core\Controllers\SimpleCrudController;
use App\Modules\Product\Models\ProductCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductCategoryController extends SimpleCrudController
{
    protected function model(): string
    {
        return ProductCategory::class;
    }

    protected function routeName(): string
    {
        return 'product-categories';
    }

    protected function viewPath(): string
    {
        return 'product.category';
    }

    protected function title(): string
    {
        return 'Kategori Produk';
    }

    protected function searchable(): array
    {
        return ['code', 'name'];
    }

    protected function orderable(): array
    {
        return [null, 'code', 'name', null, null, null];
    }

    protected function formData(): array
    {
        return [
            'parents' => ProductCategory::whereNull('parent_id')->orderBy('name')->pluck('name', 'id'),
        ];
    }

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('product_categories', 'code')
                    ->where('company_id', auth()->user()->company_id)
                    ->ignore($record?->getKey()),
            ],
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:product_categories,id'],
        ];
    }

    protected function row(Model $record): array
    {
        return [
            'code' => e($record->code),
            'name' => e($record->name),
            'parent' => e($record->parent?->name ?? '-'),
            'products' => $record->products()->count().' produk',
            'status' => $record->is_active
                ? '<span class="badge-success">Aktif</span>'
                : '<span class="badge-muted">Nonaktif</span>',
        ];
    }

    protected function beforeDelete(Model $record): ?string
    {
        if ($record->products()->exists()) {
            return 'Kategori masih dipakai oleh produk.';
        }

        return $record->children()->exists() ? 'Kategori masih memiliki sub kategori.' : null;
    }
}
