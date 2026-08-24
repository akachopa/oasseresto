<?php

declare(strict_types=1);

namespace App\Modules\Product\Controllers;

use App\Modules\Core\Controllers\SimpleCrudController;
use App\Modules\Product\Models\PriceLevel;
use App\Modules\Product\Models\ProductPrice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PriceLevelController extends SimpleCrudController
{
    protected function model(): string
    {
        return PriceLevel::class;
    }

    protected function routeName(): string
    {
        return 'price-levels';
    }

    protected function viewPath(): string
    {
        return 'product.price-level';
    }

    protected function title(): string
    {
        return 'Level Harga';
    }

    protected function searchable(): array
    {
        return ['code', 'name'];
    }

    protected function orderable(): array
    {
        return [null, 'code', 'name', 'sequence', null, null];
    }

    protected function defaults(): array
    {
        return ['is_active' => true, 'sequence' => (int) PriceLevel::max('sequence') + 10];
    }

    protected function booleans(Request $request): array
    {
        return [
            'is_active' => $request->boolean('is_active'),
            'is_default' => $request->boolean('is_default'),
        ];
    }

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('price_levels', 'code')
                    ->where('company_id', auth()->user()->company_id)
                    ->ignore($record?->getKey()),
            ],
            'name' => ['required', 'string', 'max:255'],
            'sequence' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    protected function row(Model $record): array
    {
        return [
            'code' => e($record->code),
            'name' => e($record->name),
            'sequence' => (string) $record->sequence,
            'default' => $record->is_default
                ? '<span class="badge-info">Default</span>'
                : '<span class="text-muted">-</span>',
            'status' => $record->is_active
                ? '<span class="badge-success">Aktif</span>'
                : '<span class="badge-muted">Nonaktif</span>',
        ];
    }

    protected function beforeDelete(Model $record): ?string
    {
        if ($record->is_default) {
            return 'Level harga default tidak bisa dihapus.';
        }

        return ProductPrice::where('price_level_id', $record->getKey())->exists()
            ? 'Level harga masih dipakai pada harga produk.'
            : null;
    }

    public function store(Request $request): RedirectResponse
    {
        $response = parent::store($request);
        $this->enforceSingleDefault();

        return $response;
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $response = parent::update($request, $id);
        $this->enforceSingleDefault();

        return $response;
    }

    /**
     * Hanya boleh ada satu level harga default supaya PricingService tidak
     * ambigu saat customer belum punya level sendiri.
     */
    private function enforceSingleDefault(): void
    {
        $latestDefault = PriceLevel::where('is_default', true)->orderByDesc('updated_at')->value('id');

        if ($latestDefault === null) {
            return;
        }

        PriceLevel::where('is_default', true)->where('id', '!=', $latestDefault)->update(['is_default' => false]);
    }
}
