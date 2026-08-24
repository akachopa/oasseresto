<?php

declare(strict_types=1);

namespace App\Modules\Product\Controllers;

use App\Modules\Core\Controllers\SimpleCrudController;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitController extends SimpleCrudController
{
    protected function model(): string
    {
        return Unit::class;
    }

    protected function routeName(): string
    {
        return 'units';
    }

    protected function viewPath(): string
    {
        return 'product.unit';
    }

    protected function title(): string
    {
        return 'Satuan';
    }

    protected function searchable(): array
    {
        return ['code', 'name'];
    }

    protected function orderable(): array
    {
        return [null, 'code', 'name', 'category', null, null];
    }

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('units', 'code')
                    ->where('company_id', auth()->user()->company_id)
                    ->ignore($record?->getKey()),
            ],
            'name' => ['required', 'string', 'max:60'],
            'category' => ['required', 'in:count,weight,volume,length'],
        ];
    }

    protected function row(Model $record): array
    {
        return [
            'code' => e($record->code),
            'name' => e($record->name),
            'category' => e(match ($record->category) {
                'weight' => 'Berat',
                'volume' => 'Volume',
                'length' => 'Panjang',
                default => 'Hitungan',
            }),
            'status' => $record->is_active
                ? '<span class="badge-success">Aktif</span>'
                : '<span class="badge-muted">Nonaktif</span>',
        ];
    }

    protected function beforeDelete(Model $record): ?string
    {
        $inUse = Product::where('base_unit_id', $record->getKey())
            ->orWhere('purchase_unit_id', $record->getKey())
            ->orWhere('sales_unit_id', $record->getKey())
            ->exists();

        return $inUse ? 'Satuan masih dipakai oleh produk.' : null;
    }
}
