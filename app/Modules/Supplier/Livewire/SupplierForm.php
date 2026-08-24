<?php

declare(strict_types=1);

namespace App\Modules\Supplier\Livewire;

use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Supplier\Models\SupplierProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Katalog barang per supplier dikelola dari form yang sama supaya purchasing
 * langsung tahu supplier mana yang menjual apa dan harga terakhirnya (PLAN 20).
 */
class SupplierForm extends Component
{
    public ?int $supplierId = null;

    public array $form = [
        'code' => '',
        'name' => '',
        'pic_name' => '',
        'phone' => '',
        'whatsapp' => '',
        'email' => '',
        'address' => '',
        'city' => '',
        'tax_number' => '',
        'tax_code_id' => null,
        'bank_name' => '',
        'bank_account' => '',
        'bank_account_name' => '',
        'payment_term' => 'net_30',
        'lead_time_days' => 3,
        'minimum_order_amount' => 0,
        'note' => '',
        'is_active' => true,
    ];

    /** @var array<int, array{id: ?int, product_id: ?int, unit_id: ?int, supplier_sku: ?string, last_price: float, minimum_quantity: float, lead_time_days: int, is_preferred: bool}> */
    public array $items = [];

    public function mount(?Supplier $supplier = null): void
    {
        if ($supplier?->exists) {
            $this->supplierId = $supplier->getKey();
            $this->form = array_merge($this->form, $supplier->only(array_keys($this->form)));
            $this->form['payment_term'] = $supplier->payment_term->value;

            $this->items = $supplier->products
                ->map(fn (SupplierProduct $item) => [
                    'id' => $item->getKey(),
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'supplier_sku' => $item->supplier_sku,
                    'last_price' => $item->last_price,
                    'minimum_quantity' => $item->minimum_quantity,
                    'lead_time_days' => $item->lead_time_days,
                    'is_preferred' => $item->is_preferred,
                ])->all();

            return;
        }

        $this->form['code'] = $this->nextCode();
    }

    public function addItemRow(): void
    {
        $this->items[] = [
            'id' => null,
            'product_id' => null,
            'unit_id' => null,
            'supplier_sku' => null,
            'last_price' => 0,
            'minimum_quantity' => 0,
            'lead_time_days' => (int) $this->form['lead_time_days'],
            'is_preferred' => false,
        ];
    }

    public function removeItemRow(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(): void
    {
        $companyId = (int) auth()->user()->company_id;

        $validated = $this->validate([
            'form.code' => [
                'required', 'string', 'max:30',
                Rule::unique('suppliers', 'code')->where('company_id', $companyId)->ignore($this->supplierId),
            ],
            'form.name' => ['required', 'string', 'max:255'],
            'form.pic_name' => ['nullable', 'string', 'max:100'],
            'form.phone' => ['nullable', 'string', 'max:40'],
            'form.whatsapp' => ['nullable', 'string', 'max:40'],
            'form.email' => ['nullable', 'email', 'max:255'],
            'form.address' => ['nullable', 'string'],
            'form.city' => ['nullable', 'string', 'max:100'],
            'form.tax_number' => ['nullable', 'string', 'max:40'],
            'form.tax_code_id' => ['nullable', 'integer', 'exists:tax_codes,id'],
            'form.bank_name' => ['nullable', 'string', 'max:60'],
            'form.bank_account' => ['nullable', 'string', 'max:60'],
            'form.bank_account_name' => ['nullable', 'string', 'max:100'],
            'form.payment_term' => ['required', 'in:'.implode(',', array_column(PaymentTermType::cases(), 'value'))],
            'form.lead_time_days' => ['required', 'integer', 'min:0', 'max:365'],
            'form.minimum_order_amount' => ['required', 'numeric', 'min:0'],
            'form.note' => ['nullable', 'string'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id', 'distinct'],
            'items.*.unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'items.*.supplier_sku' => ['nullable', 'string', 'max:60'],
            'items.*.last_price' => ['required', 'numeric', 'min:0'],
            'items.*.minimum_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.lead_time_days' => ['required', 'integer', 'min:0', 'max:365'],
        ], [
            'items.*.product_id.distinct' => 'Produk yang sama tidak boleh dua kali.',
        ]);

        DB::transaction(function () use ($validated): void {
            $supplier = $this->supplierId
                ? Supplier::findOrFail($this->supplierId)
                : new Supplier;

            $supplier->fill($validated['form']);
            $supplier->is_active = (bool) $this->form['is_active'];
            $supplier->save();

            $this->syncItems($supplier);
        });

        session()->flash('status', 'Supplier berhasil disimpan.');
        $this->redirectRoute('suppliers.index', navigate: true);
    }

    public function render()
    {
        return view('supplier.livewire.supplier-form', [
            'products' => Product::active()->orderBy('name')->limit(500)->get(),
            'units' => Unit::where('is_active', true)->orderBy('code')->pluck('code', 'id'),
            'taxCodes' => TaxCode::where('is_active', true)->where('for_purchase', true)->orderBy('code')->pluck('name', 'id'),
            'paymentTerms' => PaymentTermType::options(),
        ]);
    }

    private function syncItems(Supplier $supplier): void
    {
        $keep = [];

        foreach ($this->items as $row) {
            $item = SupplierProduct::updateOrCreate(
                ['supplier_id' => $supplier->getKey(), 'product_id' => $row['product_id']],
                [
                    'company_id' => $supplier->company_id,
                    'unit_id' => $row['unit_id'] ?: null,
                    'supplier_sku' => $row['supplier_sku'] ?: null,
                    'last_price' => $row['last_price'],
                    'minimum_quantity' => $row['minimum_quantity'],
                    'lead_time_days' => $row['lead_time_days'],
                    'is_preferred' => (bool) ($row['is_preferred'] ?? false),
                ],
            );

            $keep[] = $item->getKey();
        }

        SupplierProduct::where('supplier_id', $supplier->getKey())->whereNotIn('id', $keep ?: [0])->delete();
    }

    private function nextCode(): string
    {
        $last = Supplier::withTrashed()->where('code', 'like', 'SUP%')->orderByDesc('code')->value('code');
        $number = $last ? (int) substr($last, 3) : 0;

        return 'SUP'.str_pad((string) ($number + 1), 4, '0', STR_PAD_LEFT);
    }
}
