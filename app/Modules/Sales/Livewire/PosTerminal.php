<?php

declare(strict_types=1);

namespace App\Modules\Sales\Livewire;

use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Customer\Models\Customer;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Services\PricingService;
use App\Modules\Sales\Models\CashierShift;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Services\PosService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use RuntimeException;

/**
 * Layar kasir: pencarian cepat, keranjang, dan pembayaran dalam satu halaman
 * agar transaksi counter selesai tanpa pindah layar (PLAN 28).
 */
class PosTerminal extends Component
{
    public int $shiftId;

    public string $search = '';

    public ?int $customerId = null;

    public string $paymentMethod = 'cash';

    public float $paidAmount = 0;

    /** @var array<int, array{product_id: int, sku: string, name: string, unit: string, unit_id: int, quantity: float, unit_price: float}> */
    public array $cart = [];

    public ?int $lastInvoiceId = null;

    public float $lastChange = 0;

    public function mount(CashierShift $shift): void
    {
        $this->shiftId = $shift->getKey();

        // Customer walk-in dipilih lebih dulu agar transaksi counter cepat.
        $this->customerId = Customer::active()->where('type', 'walk_in')->value('id')
            ?? Customer::active()->orderBy('name')->value('id');
    }

    public function addProduct(int $productId): void
    {
        $product = Product::find($productId);

        if ($product === null) {
            return;
        }

        foreach ($this->cart as $index => $row) {
            if ($row['product_id'] === $productId) {
                $this->cart[$index]['quantity'] = round($row['quantity'] + 1, 4);

                return;
            }
        }

        $unitId = (int) ($product->sales_unit_id ?? $product->base_unit_id);

        $this->cart[] = [
            'product_id' => (int) $product->getKey(),
            'sku' => (string) $product->sku,
            'name' => (string) $product->name,
            'unit' => (string) ($product->units->firstWhere('unit_id', $unitId)?->unit?->code
                ?? $product->baseUnit?->code),
            'unit_id' => $unitId,
            'quantity' => 1,
            'unit_price' => $this->priceFor($product, $unitId),
        ];

        $this->search = '';
    }

    public function removeLine(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function updateQuantity(int $index, float $quantity): void
    {
        if ($quantity <= 0) {
            $this->removeLine($index);

            return;
        }

        $this->cart[$index]['quantity'] = round($quantity, 4);
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->paidAmount = 0;
        $this->lastInvoiceId = null;
    }

    public function checkout(): void
    {
        $this->validate([
            'customerId' => ['required', 'integer', 'exists:customers,id'],
            'paymentMethod' => ['required', 'in:cash,transfer,card'],
            'paidAmount' => ['required', 'numeric', 'min:0'],
        ]);

        if ($this->cart === []) {
            $this->addError('cart', 'Keranjang masih kosong.');

            return;
        }

        try {
            $invoice = app(PosService::class)->checkout(
                $this->shift(),
                Customer::findOrFail($this->customerId),
                collect($this->cart)
                    ->map(fn (array $row) => [
                        'product_id' => $row['product_id'],
                        'unit_id' => $row['unit_id'],
                        'quantity' => $row['quantity'],
                        'unit_price' => $row['unit_price'],
                    ])
                    ->all(),
                (float) $this->paidAmount,
                $this->paymentMethod,
            );
        } catch (RuntimeException $exception) {
            $this->addError('cart', $exception->getMessage());

            return;
        }

        $this->lastChange = app(PosService::class)->change($invoice, (float) $this->paidAmount);
        $this->lastInvoiceId = $invoice->getKey();
        $this->cart = [];
        $this->paidAmount = 0;
    }

    public function render()
    {
        $shift = $this->shift();

        return view('sales.livewire.pos-terminal', [
            'shift' => $shift,
            'customers' => Customer::active()->orderBy('name')->limit(200)->get(),
            'results' => $this->searchResults(),
            'total' => $this->total(),
            'lastInvoice' => $this->lastInvoiceId ? SalesInvoice::find($this->lastInvoiceId) : null,
        ]);
    }

    public function total(): float
    {
        return round(collect($this->cart)->sum(
            fn (array $row) => $row['quantity'] * $row['unit_price'],
        ), 4);
    }

    private function searchResults()
    {
        $term = trim($this->search);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        $shift = $this->shift();
        $stock = app(StockService::class);

        return Product::active()
            ->where(fn ($query) => $query
                ->where('sku', 'ilike', "%{$term}%")
                ->orWhere('name', 'ilike', "%{$term}%")
                ->orWhere('barcode', 'ilike', "%{$term}%"))
            ->orderBy('name')
            ->limit(12)
            ->get()
            ->map(function (Product $product) use ($stock, $shift) {
                $product->setAttribute(
                    'available_quantity',
                    $stock->available((int) $product->getKey(), (int) $shift->warehouse_id),
                );

                return $product;
            });
    }

    private function priceFor(Product $product, int $unitId): float
    {
        return app(PricingService::class)->quote(
            product: $product,
            customer: $this->customerId ? Customer::find($this->customerId) : null,
            unitId: $unitId,
            paymentTerm: PaymentTermType::Cash,
        )->price;
    }

    private function shift(): CashierShift
    {
        $shift = CashierShift::findOrFail($this->shiftId);

        abort_unless((int) $shift->user_id === (int) Auth::id(), 403);

        return $shift;
    }
}
