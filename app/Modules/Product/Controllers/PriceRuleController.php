<?php

declare(strict_types=1);

namespace App\Modules\Product\Controllers;

use App\Modules\Company\Models\Branch;
use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Enums\PriceRuleType;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerGroup;
use App\Modules\Product\Models\PriceLevel;
use App\Modules\Product\Models\PriceRule;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductCategory;
use App\Modules\Product\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Aturan harga punya banyak dimensi (PLAN 15), jadi tidak memakai
 * SimpleCrudController agar form dan validasinya bisa spesifik.
 */
class PriceRuleController
{
    public function index(): View
    {
        return view('product.price-rule.index', [
            'types' => PriceRuleType::cases(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = PriceRule::query()
            ->leftJoin('products', 'products.id', '=', 'price_rules.product_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'price_rules.product_category_id')
            ->leftJoin('customers', 'customers.id', '=', 'price_rules.customer_id')
            ->leftJoin('customer_groups', 'customer_groups.id', '=', 'price_rules.customer_group_id')
            ->select([
                'price_rules.*',
                'products.name as product_name',
                'product_categories.name as category_name',
                'customers.name as customer_name',
                'customer_groups.name as group_name',
            ]);

        return response()->json(
            ServerTable::of($query)
                ->searchable(['price_rules.name', 'products.name', 'customers.name'])
                ->orderable([
                    null, 'price_rules.name', 'price_rules.type', null, null,
                    'price_rules.min_quantity', 'price_rules.valid_from', null, null,
                ])
                ->filter('type', fn ($q, $value) => $q->where('price_rules.type', $value))
                ->filter('is_active', fn ($q, $value) => $q->where('price_rules.is_active', $value === '1'))
                ->transform(fn (PriceRule $rule) => [
                    'name' => '<span class="font-medium">'.e($rule->name).'</span>',
                    'type' => '<span class="badge-info">'.e($rule->type->label()).'</span>',
                    'target' => e($this->targetLabel($rule)),
                    'applies_to' => e($this->scopeLabel($rule)),
                    'quantity' => e($this->quantityLabel($rule)),
                    'period' => e($this->periodLabel($rule)),
                    'value' => $this->valueLabel($rule),
                    'status' => $rule->is_active
                        ? '<span class="badge-success">Aktif</span>'
                        : '<span class="badge-muted">Nonaktif</span>',
                    'aksi' => view('components.row-actions', [
                        'edit' => route('price-rules.edit', $rule),
                        'delete' => route('price-rules.hapus', $rule),
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(): View
    {
        return view('product.price-rule.form', [
            'record' => new PriceRule([
                'type' => PriceRuleType::QuantityTier,
                'mode' => 'discount_percent',
                'min_quantity' => 0,
                'priority' => 0,
                'is_active' => true,
            ]),
            ...$this->formData(),
        ]);
    }

    public function edit(PriceRule $priceRule): View
    {
        return view('product.price-rule.form', [
            'record' => $priceRule,
            ...$this->formData(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        PriceRule::create($this->validated($request));

        return redirect()->route('price-rules.index')->with('status', 'Aturan harga berhasil ditambahkan.');
    }

    public function update(Request $request, PriceRule $priceRule): RedirectResponse
    {
        $priceRule->update($this->validated($request));

        return redirect()->route('price-rules.index')->with('status', 'Aturan harga berhasil diperbarui.');
    }

    public function hapus(PriceRule $priceRule): RedirectResponse
    {
        $priceRule->delete();

        return back()->with('status', 'Aturan harga berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:'.implode(',', array_column(PriceRuleType::cases(), 'value'))],
            'priority' => ['required', 'integer', 'min:0', 'max:999'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'price_level_id' => ['nullable', 'integer', 'exists:price_levels,id'],
            'customer_group_id' => ['nullable', 'integer', 'exists:customer_groups,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'payment_term' => ['nullable', 'string', 'in:'.implode(',', array_column(PaymentTermType::cases(), 'value'))],
            'min_quantity' => ['required', 'numeric', 'min:0'],
            'max_quantity' => ['nullable', 'numeric', 'gte:min_quantity'],
            'mode' => ['required', 'in:fixed,discount_percent,discount_amount'],
            'price' => ['nullable', 'numeric', 'min:0', 'required_if:mode,fixed'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_if:mode,discount_percent'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'required_if:mode,discount_amount'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ], [
            'price.required_if' => 'Harga tetap wajib diisi untuk mode harga tetap.',
            'discount_percent.required_if' => 'Persentase diskon wajib diisi.',
            'discount_amount.required_if' => 'Nominal diskon wajib diisi.',
        ]);

        // Hanya satu kolom nilai yang relevan per mode, sisanya dikosongkan
        // supaya tidak ada nilai yatim yang menyesatkan saat audit harga.
        $data['price'] = $data['mode'] === 'fixed' ? $data['price'] : null;
        $data['discount_percent'] = $data['mode'] === 'discount_percent' ? $data['discount_percent'] : null;
        $data['discount_amount'] = $data['mode'] === 'discount_amount' ? $data['discount_amount'] : null;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'types' => PriceRuleType::cases(),
            'products' => Product::active()->orderBy('name')->limit(500)->get(),
            'categories' => ProductCategory::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'priceLevels' => PriceLevel::where('is_active', true)->orderBy('sequence')->pluck('name', 'id'),
            'customerGroups' => CustomerGroup::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'customers' => Customer::active()->orderBy('name')->limit(500)->get(),
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'units' => Unit::where('is_active', true)->orderBy('code')->pluck('code', 'id'),
            'paymentTerms' => PaymentTermType::options(),
        ];
    }

    private function targetLabel(PriceRule $rule): string
    {
        return match (true) {
            $rule->product_name !== null => (string) $rule->product_name,
            $rule->category_name !== null => 'Kategori: '.$rule->category_name,
            default => 'Semua produk',
        };
    }

    private function scopeLabel(PriceRule $rule): string
    {
        return match (true) {
            $rule->customer_name !== null => (string) $rule->customer_name,
            $rule->group_name !== null => 'Grup: '.$rule->group_name,
            $rule->payment_term !== null => 'Termin: '.PaymentTermType::from($rule->payment_term)->label(),
            default => 'Semua customer',
        };
    }

    private function quantityLabel(PriceRule $rule): string
    {
        if ($rule->min_quantity <= 0 && $rule->max_quantity === null) {
            return 'Semua qty';
        }

        return Money::quantity($rule->min_quantity).' - '
            .($rule->max_quantity === null ? '~' : Money::quantity($rule->max_quantity));
    }

    private function periodLabel(PriceRule $rule): string
    {
        if ($rule->valid_from === null && $rule->valid_to === null) {
            return 'Tanpa batas';
        }

        return ($rule->valid_from?->format('d/m/Y') ?? '~').' - '.($rule->valid_to?->format('d/m/Y') ?? '~');
    }

    private function valueLabel(PriceRule $rule): string
    {
        return match ($rule->mode) {
            'fixed' => Money::rupiah($rule->price),
            'discount_percent' => 'Diskon '.Money::percent($rule->discount_percent),
            'discount_amount' => 'Diskon '.Money::rupiah($rule->discount_amount),
            default => '-',
        };
    }
}
