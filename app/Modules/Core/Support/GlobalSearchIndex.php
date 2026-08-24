<?php

declare(strict_types=1);

namespace App\Modules\Core\Support;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Asset\Models\FixedAsset;
use App\Modules\Customer\Models\Customer;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\Receipt;
use App\Modules\Product\Models\Product;
use App\Modules\Purchase\Models\GoodsReceipt;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Supplier\Models\Supplier;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * Pencarian lintas entity untuk command palette. Setiap sumber punya
 * permission dan route detail sendiri sehingga hasil tidak bocor antar role.
 */
class GlobalSearchIndex
{
    /**
     * @return array<int, array{label: string, items: array<int, array{title: string, subtitle: string, url: ?string}>}>
     */
    public function search(string $term): array
    {
        $groups = [];

        foreach ($this->sources() as $source) {
            if (! class_exists($source['model']) || ! Auth::user()?->can($source['permission'])) {
                continue;
            }

            /** @var Builder $query */
            $query = $source['model']::query();

            $items = $query
                ->where(function (Builder $builder) use ($source, $term): void {
                    foreach ($source['columns'] as $column) {
                        $builder->orWhere($column, 'ilike', "%{$term}%");
                    }
                })
                ->limit(5)
                ->get()
                ->map(fn ($model) => [
                    'title' => $this->stringify($model->{$source['title']}),
                    'subtitle' => $source['subtitle'] ? $this->stringify($model->{$source['subtitle']}) : '',
                    'url' => $source['route'] && Route::has($source['route'])
                        ? route($source['route'], $model)
                        : null,
                ])
                ->all();

            if ($items !== []) {
                $groups[] = ['label' => $source['label'], 'items' => $items];
            }
        }

        return $groups;
    }

    /**
     * @return array<int, array{label: string, model: string, permission: string, columns: array<int, string>, title: string, subtitle: ?string, route: ?string}>
     */
    private function sources(): array
    {
        return [
            [
                'label' => 'Produk',
                'model' => Product::class,
                'permission' => 'product.view',
                'columns' => ['sku', 'name', 'barcode'],
                'title' => 'name',
                'subtitle' => 'sku',
                'route' => 'products.detail',
            ],
            [
                'label' => 'Customer',
                'model' => Customer::class,
                'permission' => 'customer.view',
                'columns' => ['code', 'name', 'phone'],
                'title' => 'name',
                'subtitle' => 'code',
                'route' => 'customers.detail',
            ],
            [
                'label' => 'Supplier',
                'model' => Supplier::class,
                'permission' => 'supplier.view',
                'columns' => ['code', 'name', 'phone'],
                'title' => 'name',
                'subtitle' => 'code',
                'route' => 'suppliers.detail',
            ],
            [
                'label' => 'Order Penjualan',
                'model' => SalesOrder::class,
                'permission' => 'sales.view',
                'columns' => ['number'],
                'title' => 'number',
                'subtitle' => 'status',
                'route' => 'sales.orders.detail',
            ],
            [
                'label' => 'Invoice Penjualan',
                'model' => SalesInvoice::class,
                'permission' => 'sales.view',
                'columns' => ['number'],
                'title' => 'number',
                'subtitle' => 'status',
                'route' => 'sales.invoices.detail',
            ],
            [
                'label' => 'Purchase Order',
                'model' => PurchaseOrder::class,
                'permission' => 'purchase.view',
                'columns' => ['number'],
                'title' => 'number',
                'subtitle' => 'status',
                'route' => 'purchase.orders.detail',
            ],
            [
                'label' => 'Penerimaan Barang',
                'model' => GoodsReceipt::class,
                'permission' => 'inventory.receive',
                'columns' => ['number'],
                'title' => 'number',
                'subtitle' => 'status',
                'route' => 'purchase.receipts.detail',
            ],
            [
                'label' => 'Penerimaan Kas',
                'model' => Receipt::class,
                'permission' => 'finance.receivable.collect',
                'columns' => ['number', 'reference'],
                'title' => 'number',
                'subtitle' => 'status',
                'route' => 'finance.receipts.detail',
            ],
            [
                'label' => 'Pembayaran',
                'model' => Payment::class,
                'permission' => 'finance.payable.pay',
                'columns' => ['number', 'reference'],
                'title' => 'number',
                'subtitle' => 'status',
                'route' => 'finance.payments.detail',
            ],
            [
                'label' => 'Jurnal',
                'model' => JournalEntry::class,
                'permission' => 'accounting.journal.view',
                'columns' => ['number', 'description'],
                'title' => 'number',
                'subtitle' => 'description',
                'route' => 'accounting.journals.detail',
            ],
            [
                'label' => 'Aset Tetap',
                'model' => FixedAsset::class,
                'permission' => 'asset.view',
                'columns' => ['code', 'name'],
                'title' => 'name',
                'subtitle' => 'code',
                'route' => null,
            ],
        ];
    }

    private function stringify(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return method_exists($value, 'label') ? $value->label() : (string) $value->value;
        }

        return (string) $value;
    }
}
