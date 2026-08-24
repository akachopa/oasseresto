<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Livewire;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use App\Modules\Purchase\Models\PurchaseInvoice;
use App\Modules\Purchase\Models\PurchaseReturn;
use App\Modules\Purchase\Models\PurchaseReturnItem;
use App\Modules\Purchase\Services\PurchaseReturnService;
use App\Modules\Supplier\Models\Supplier;
use Livewire\Component;
use RuntimeException;

class ReturnForm extends Component
{
    public ?int $returnId = null;

    public array $form = [
        'purchase_invoice_id' => null,
        'supplier_id' => null,
        'warehouse_id' => null,
        'return_date' => '',
        'reason' => 'damaged',
        'settlement' => 'credit_note',
        'note' => '',
    ];

    /** @var array<int, array{product_id: ?int, unit_id: ?int, batch_id: ?int, quantity: float, unit_price: ?float, tax_code_id: ?int, note: ?string}> */
    public array $items = [];

    public function mount(?PurchaseReturn $return = null, ?int $invoiceId = null): void
    {
        if ($return?->exists) {
            $this->returnId = $return->getKey();
            $this->form = [
                'purchase_invoice_id' => $return->purchase_invoice_id,
                'supplier_id' => $return->supplier_id,
                'warehouse_id' => $return->warehouse_id,
                'return_date' => $return->return_date->toDateString(),
                'reason' => $return->reason,
                'settlement' => $return->settlement,
                'note' => $return->note,
            ];

            $this->items = $return->items
                ->map(fn (PurchaseReturnItem $item) => [
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'batch_id' => $item->batch_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
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
        if ($key === 'purchase_invoice_id' && $value) {
            $this->loadInvoice((int) $value);
        }
    }

    public function addItemRow(): void
    {
        $this->items[] = [
            'product_id' => null,
            'unit_id' => null,
            'batch_id' => null,
            'quantity' => 1,
            'unit_price' => null,
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

        $this->items[$index]['unit_id'] = $this->items[$index]['unit_id'] ?: $product->base_unit_id;
        $this->items[$index]['batch_id'] = null;
        $this->items[$index]['unit_price'] = $product->last_purchase_cost ?: $product->average_cost;
        $this->items[$index]['tax_code_id'] = $product->tax_code_id;
    }

    public function save(bool $andPost = false): void
    {
        $validated = $this->validate([
            'form.purchase_invoice_id' => ['nullable', 'integer', 'exists:purchase_invoices,id'],
            'form.supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'form.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'form.return_date' => ['required', 'date'],
            'form.reason' => ['required', 'in:'.implode(',', array_keys(PurchaseReturn::reasons()))],
            'form.settlement' => ['required', 'in:'.implode(',', array_keys(PurchaseReturn::settlements()))],
            'form.note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_code_id' => ['nullable', 'integer', 'exists:tax_codes,id'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ], [
            'items.required' => 'Minimal satu baris barang.',
        ]);

        try {
            $returns = app(PurchaseReturnService::class);

            $return = $returns->save(
                $this->returnId ? PurchaseReturn::findOrFail($this->returnId) : null,
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
            ? 'Retur diposting; stok keluar dan hutang disesuaikan.'
            : 'Retur pembelian disimpan sebagai draft.');

        $this->redirectRoute('purchase.returns.detail', $return, navigate: true);
    }

    public function render()
    {
        return view('purchase.livewire.return-form', [
            'invoices' => $this->postedInvoices(),
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'warehouses' => Warehouse::active()->orderBy('name')->get(),
            'products' => Product::active()->stocked()->orderBy('name')->limit(500)->get(),
            'units' => Unit::where('is_active', true)->orderBy('code')->pluck('code', 'id'),
            'taxCodes' => TaxCode::where('is_active', true)->where('for_purchase', true)->orderBy('code')->get(),
            'reasons' => PurchaseReturn::reasons(),
            'settlements' => PurchaseReturn::settlements(),
            'batchOptions' => $this->batchOptions(),
            'availability' => $this->availability(),
        ]);
    }

    /**
     * Retur diambil dari invoice supaya barang yang dikembalikan memang
     * pernah ditagih supplier tersebut.
     */
    private function loadInvoice(int $invoiceId): void
    {
        $invoice = PurchaseInvoice::with('items.product')->find($invoiceId);

        if ($invoice === null || ! $invoice->isPosted()) {
            $this->addError('form.purchase_invoice_id', 'Invoice ini belum diposting.');

            return;
        }

        $this->form['purchase_invoice_id'] = $invoice->getKey();
        $this->form['supplier_id'] = $invoice->supplier_id;

        $this->items = $invoice->items
            ->map(fn ($item) => [
                'product_id' => $item->product_id,
                'unit_id' => $item->unit_id,
                'batch_id' => null,
                'quantity' => 0,
                'unit_price' => $item->unit_price,
                'tax_code_id' => $item->tax_code_id,
                'note' => null,
            ])
            ->values()
            ->all();

        if ($this->items === []) {
            $this->addItemRow();
        }
    }

    private function postedInvoices()
    {
        return PurchaseInvoice::query()
            ->where('status', DocumentStatus::Posted)
            ->with('supplier')
            ->orderByDesc('invoice_date')
            ->limit(200)
            ->get();
    }

    /**
     * @return array<int, array<int, Batch>>
     */
    private function batchOptions(): array
    {
        $productIds = array_filter(array_column($this->items, 'product_id'));

        if ($productIds === [] || ! $this->form['warehouse_id']) {
            return [];
        }

        return Batch::whereIn('product_id', $productIds)
            ->where('warehouse_id', $this->form['warehouse_id'])
            ->available()
            ->fefo()
            ->get()
            ->groupBy('product_id')
            ->map(fn ($batches) => $batches->all())
            ->all();
    }

    /**
     * @return array<int, float>
     */
    private function availability(): array
    {
        if (! $this->form['warehouse_id']) {
            return [];
        }

        $stock = app(StockService::class);

        return collect($this->items)
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->mapWithKeys(fn ($productId) => [
                (int) $productId => $stock->available((int) $productId, (int) $this->form['warehouse_id']),
            ])
            ->all();
    }

    private function defaultWarehouseId(): ?int
    {
        $branchId = app(ScopeManager::class)->branchId();

        return Warehouse::active()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('is_default')
            ->value('id');
    }
}
