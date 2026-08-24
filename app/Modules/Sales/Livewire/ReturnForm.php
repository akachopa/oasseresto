<?php

declare(strict_types=1);

namespace App\Modules\Sales\Livewire;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Customer\Models\Customer;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesReturn;
use App\Modules\Sales\Models\SalesReturnItem;
use App\Modules\Sales\Services\SalesLineSummary;
use App\Modules\Sales\Services\SalesReturnService;
use Livewire\Component;
use RuntimeException;

class ReturnForm extends Component
{
    public ?int $returnId = null;

    public array $form = [
        'sales_invoice_id' => null,
        'customer_id' => null,
        'warehouse_id' => null,
        'return_date' => '',
        'reason' => 'not_as_ordered',
        'settlement' => 'credit_note',
        'restock' => true,
        'note' => '',
    ];

    /** @var array<int, array{sales_invoice_item_id: ?int, product_id: ?int, unit_id: ?int, quantity: float, unit_price: ?float, unit_cost: ?float, tax_code_id: ?int, note: ?string}> */
    public array $items = [];

    public function mount(?SalesReturn $return = null, ?int $invoiceId = null): void
    {
        if ($return?->exists) {
            $this->returnId = $return->getKey();
            $this->form = [
                'sales_invoice_id' => $return->sales_invoice_id,
                'customer_id' => $return->customer_id,
                'warehouse_id' => $return->warehouse_id,
                'return_date' => $return->return_date->toDateString(),
                'reason' => $return->reason,
                'settlement' => $return->settlement,
                'restock' => (bool) $return->restock,
                'note' => $return->note,
            ];

            $this->items = $return->items
                ->map(fn (SalesReturnItem $item) => [
                    'sales_invoice_item_id' => $item->sales_invoice_item_id,
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'unit_cost' => $item->unit_cost,
                    'tax_code_id' => $item->tax_code_id,
                    'note' => $item->note,
                ])->all();

            return;
        }

        $this->form['return_date'] = now()->toDateString();
        $this->form['warehouse_id'] = $this->defaultWarehouseId();

        if ($invoiceId !== null) {
            $this->loadInvoice($invoiceId);

            return;
        }

        $this->addItemRow();
    }

    public function updatedForm(mixed $value, string $key): void
    {
        if ($key === 'sales_invoice_id' && $value) {
            $this->loadInvoice((int) $value);
        }

        // Barang rusak atau kedaluwarsa tidak layak dijual ulang.
        if ($key === 'reason') {
            $this->form['restock'] = ! in_array($value, ['damaged', 'expired'], true);
        }
    }

    public function addItemRow(): void
    {
        $this->items[] = [
            'sales_invoice_item_id' => null,
            'product_id' => null,
            'unit_id' => null,
            'quantity' => 1,
            'unit_price' => null,
            'unit_cost' => null,
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
        $product = Product::find($value);

        if ($product === null) {
            return;
        }

        $unitId = (int) ($this->items[$index]['unit_id'] ?: ($product->sales_unit_id ?? $product->base_unit_id));

        $this->items[$index]['unit_id'] = $unitId;
        $this->items[$index]['unit_price'] = round((float) $product->base_price * $product->conversionFor($unitId), 4);
        $this->items[$index]['unit_cost'] = round((float) $product->average_cost * $product->conversionFor($unitId), 4);
        $this->items[$index]['tax_code_id'] = $product->tax_code_id;
    }

    public function save(bool $andPost = false): void
    {
        $validated = $this->validate([
            'form.sales_invoice_id' => ['nullable', 'integer', 'exists:sales_invoices,id'],
            'form.customer_id' => ['required', 'integer', 'exists:customers,id'],
            'form.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'form.return_date' => ['required', 'date'],
            'form.reason' => ['required', 'in:'.implode(',', array_keys(SalesReturn::reasons()))],
            'form.settlement' => ['required', 'in:'.implode(',', array_keys(SalesReturn::settlements()))],
            'form.restock' => ['boolean'],
            'form.note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sales_invoice_item_id' => ['nullable', 'integer', 'exists:sales_invoice_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_code_id' => ['nullable', 'integer', 'exists:tax_codes,id'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ], [
            'items.required' => 'Minimal satu baris barang.',
        ]);

        if (collect($validated['items'])->sum('quantity') <= 0) {
            $this->addError('items', 'Isi kuantitas minimal satu baris barang yang diretur.');

            return;
        }

        try {
            $returns = app(SalesReturnService::class);

            $return = $returns->save(
                $this->returnId ? SalesReturn::findOrFail($this->returnId) : null,
                $validated['form'],
                $validated['items'],
            );

            if ($andPost) {
                $returns->post($return);
            }
        } catch (RuntimeException $exception) {
            $this->addError('items', $exception->getMessage());

            return;
        }

        session()->flash('status', $andPost
            ? 'Retur diposting; stok dan piutang sudah disesuaikan.'
            : 'Retur penjualan disimpan sebagai draft.');

        $this->redirectRoute('sales.returns.detail', $return, navigate: true);
    }

    public function render()
    {
        return view('sales.livewire.return-form', [
            'invoices' => $this->postedInvoices(),
            'customers' => Customer::active()->orderBy('name')->get(),
            'warehouses' => Warehouse::active()->orderBy('name')->get(),
            'products' => Product::active()->orderBy('name')->limit(500)->get(),
            'units' => Unit::where('is_active', true)->orderBy('code')->pluck('code', 'id'),
            'taxCodes' => TaxCode::where('is_active', true)->where('for_sales', true)->orderBy('code')->get(),
            'reasons' => SalesReturn::reasons(),
            'settlements' => SalesReturn::settlements(),
            'returnable' => $this->returnableByInvoiceItem(),
            'summary' => app(SalesLineSummary::class)->of($this->items),
        ]);
    }

    private function loadInvoice(int $invoiceId): void
    {
        $invoice = SalesInvoice::with('items.product')->find($invoiceId);

        if ($invoice === null || ! $invoice->isPosted()) {
            $this->addError('form.sales_invoice_id', 'Invoice ini belum diposting.');

            return;
        }

        $this->form['sales_invoice_id'] = $invoice->getKey();
        $this->form['customer_id'] = $invoice->customer_id;
        $this->form['warehouse_id'] = $invoice->warehouse_id ?: $this->form['warehouse_id'];
        $this->items = app(SalesReturnService::class)->draftItemsFromInvoice($invoice);

        if ($this->items === []) {
            $this->addError('form.sales_invoice_id', 'Seluruh barang pada invoice ini sudah diretur.');
            $this->addItemRow();
        }
    }

    /**
     * @return array<int, float>
     */
    private function returnableByInvoiceItem(): array
    {
        if (! $this->form['sales_invoice_id']) {
            return [];
        }

        $invoice = SalesInvoice::with('items')->find($this->form['sales_invoice_id']);

        return $invoice === null
            ? []
            : $invoice->items
                ->mapWithKeys(fn ($item) => [$item->getKey() => $item->returnableBaseQuantity()])
                ->all();
    }

    private function postedInvoices()
    {
        return SalesInvoice::query()
            ->where('status', DocumentStatus::Posted)
            ->with('customer')
            ->orderByDesc('invoice_date')
            ->limit(200)
            ->get();
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
