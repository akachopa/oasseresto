<?php

declare(strict_types=1);

namespace App\Modules\Sales\Livewire;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Customer\Models\Customer;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use App\Modules\Product\Services\PricingService;
use App\Modules\Sales\Models\Quotation;
use App\Modules\Sales\Models\QuotationItem;
use App\Modules\Sales\Services\QuotationService;
use App\Modules\Sales\Services\SalesLineSummary;
use Livewire\Component;
use RuntimeException;

class QuotationForm extends Component
{
    public ?int $quotationId = null;

    public array $form = [
        'customer_id' => null,
        'warehouse_id' => null,
        'quotation_date' => '',
        'valid_until' => null,
        'payment_term' => PaymentTermType::Net30->value,
        'is_tax_inclusive' => false,
        'note' => '',
        'terms' => '',
    ];

    /** @var array<int, array{product_id: ?int, unit_id: ?int, quantity: float, unit_price: ?float, discount_percent: ?float, tax_code_id: ?int, note: ?string}> */
    public array $items = [];

    public function mount(?Quotation $quotation = null, ?int $customerId = null): void
    {
        if ($quotation?->exists) {
            $this->quotationId = $quotation->getKey();
            $this->form = [
                'customer_id' => $quotation->customer_id,
                'warehouse_id' => $quotation->warehouse_id,
                'quotation_date' => $quotation->quotation_date->toDateString(),
                'valid_until' => $quotation->valid_until?->toDateString(),
                'payment_term' => $quotation->payment_term->value,
                'is_tax_inclusive' => (bool) $quotation->is_tax_inclusive,
                'note' => $quotation->note,
                'terms' => $quotation->terms,
            ];

            $this->items = $quotation->items
                ->map(fn (QuotationItem $item) => [
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'tax_code_id' => $item->tax_code_id,
                    'note' => $item->note,
                ])->all();

            return;
        }

        $this->form['quotation_date'] = now()->toDateString();
        $this->form['valid_until'] = now()->addDays(14)->toDateString();
        $this->form['warehouse_id'] = $this->defaultWarehouseId();
        $this->form['customer_id'] = $customerId;
        $this->applyCustomerDefaults();
        $this->addItemRow();
    }

    public function updatedForm(mixed $value, string $key): void
    {
        if ($key === 'customer_id') {
            $this->applyCustomerDefaults();
            $this->repriceItems();
        }
    }

    public function addItemRow(): void
    {
        $this->items[] = [
            'product_id' => null,
            'unit_id' => null,
            'quantity' => 1,
            'unit_price' => null,
            'discount_percent' => 0,
            'tax_code_id' => null,
            'note' => null,
        ];
    }

    public function removeItemRow(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function updatedItems(mixed $value, string $key): void
    {
        if (! str_ends_with($key, '.product_id') || ! $value) {
            return;
        }

        $index = (int) explode('.', $key)[0];
        $this->applyPrice($index);
    }

    public function save(bool $andSend = false): void
    {
        $validated = $this->validate([
            'form.customer_id' => ['required', 'integer', 'exists:customers,id'],
            'form.warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'form.quotation_date' => ['required', 'date'],
            'form.valid_until' => ['nullable', 'date', 'after_or_equal:form.quotation_date'],
            'form.payment_term' => ['required', 'in:'.implode(',', array_keys(PaymentTermType::options()))],
            'form.is_tax_inclusive' => ['boolean'],
            'form.note' => ['nullable', 'string'],
            'form.terms' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'items.*.tax_code_id' => ['nullable', 'integer', 'exists:tax_codes,id'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ], [
            'form.valid_until.after_or_equal' => 'Masa berlaku tidak boleh sebelum tanggal penawaran.',
            'items.required' => 'Minimal satu baris barang.',
        ]);

        try {
            $quotations = app(QuotationService::class);

            $quotation = $quotations->save(
                $this->quotationId ? Quotation::findOrFail($this->quotationId) : null,
                $validated['form'],
                $validated['items'],
            );

            if ($andSend) {
                $quotations->send($quotation);
            }
        } catch (RuntimeException $exception) {
            $this->addError('items', $exception->getMessage());

            return;
        }

        session()->flash('status', $andSend
            ? 'Penawaran disimpan dan ditandai terkirim.'
            : 'Penawaran disimpan sebagai draft.');

        $this->redirectRoute('sales.quotations.detail', $quotation, navigate: true);
    }

    public function render()
    {
        return view('sales.livewire.quotation-form', [
            'customers' => Customer::active()->orderBy('name')->get(),
            'warehouses' => Warehouse::active()->orderBy('name')->get(),
            'products' => Product::active()->orderBy('name')->limit(500)->get(),
            'units' => Unit::where('is_active', true)->orderBy('code')->pluck('code', 'id'),
            'taxCodes' => TaxCode::where('is_active', true)->where('for_sales', true)->orderBy('code')->get(),
            'paymentTerms' => PaymentTermType::options(),
            'summary' => app(SalesLineSummary::class)->of(
                $this->items,
                (bool) $this->form['is_tax_inclusive'],
            ),
        ]);
    }

    private function applyCustomerDefaults(): void
    {
        if (! $this->form['customer_id']) {
            return;
        }

        $customer = Customer::find($this->form['customer_id']);

        if ($customer !== null) {
            $this->form['payment_term'] = $customer->effectivePaymentTerm()->value;
        }
    }

    private function repriceItems(): void
    {
        foreach (array_keys($this->items) as $index) {
            if ($this->items[$index]['product_id']) {
                $this->applyPrice($index);
            }
        }
    }

    /**
     * Harga selalu diambil dari PricingService supaya level harga customer
     * dan aturan promo berlaku (PLAN 15).
     */
    private function applyPrice(int $index): void
    {
        $product = Product::find($this->items[$index]['product_id']);

        if ($product === null) {
            return;
        }

        $unitId = (int) ($this->items[$index]['unit_id'] ?: ($product->sales_unit_id ?? $product->base_unit_id));

        $quote = app(PricingService::class)->quote(
            product: $product,
            quantity: (float) ($this->items[$index]['quantity'] ?: 1),
            customer: $this->form['customer_id'] ? Customer::find($this->form['customer_id']) : null,
            unitId: $unitId,
            paymentTerm: PaymentTermType::tryFrom((string) $this->form['payment_term']),
        );

        $this->items[$index]['unit_id'] = $unitId;
        $this->items[$index]['unit_price'] = $quote->price;
        $this->items[$index]['tax_code_id'] = $product->tax_code_id;
    }

    private function defaultWarehouseId(): ?int
    {
        $branchId = app(ScopeManager::class)->branchId();

        return Warehouse::active()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderByDesc('is_default')
            ->value('id');
    }
}
