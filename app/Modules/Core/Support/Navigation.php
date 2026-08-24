<?php

declare(strict_types=1);

namespace App\Modules\Core\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Menu dibangun berbasis pekerjaan, bukan struktur database (PLAN 5 dan 7).
 * Item disembunyikan bila user tidak punya permission atau bila route-nya
 * belum tersedia, sehingga menu selalu konsisten dengan modul yang aktif.
 */
class Navigation
{
    /**
     * @return array<int, array{label: string, icon: string, route?: string, items: array<int, array{label: string, route: string, permission: ?string}>}>
     */
    public static function sidebar(?User $user): array
    {
        return array_values(array_filter(
            array_map(
                fn (array $group) => self::filterGroup($group, $user),
                self::definition(),
            ),
            fn (?array $group) => $group !== null,
        ));
    }

    /**
     * Bottom navigation mobile maksimal 5 item dan bergantung role (PLAN 8).
     *
     * @return array<int, array{label: string, route: string, icon: string}>
     */
    public static function bottom(?User $user): array
    {
        $sets = [
            'Cashier' => [
                ['label' => 'Kasir', 'route' => 'pos.index', 'icon' => 'cash-register'],
                ['label' => 'Pesanan', 'route' => 'sales.orders.index', 'icon' => 'receipt'],
                ['label' => 'Customer', 'route' => 'customers.index', 'icon' => 'users'],
                ['label' => 'Shift', 'route' => 'pos.shift', 'icon' => 'clock'],
            ],
            'Warehouse Staff' => [
                ['label' => 'Home', 'route' => 'home.warehouse', 'icon' => 'home'],
                ['label' => 'Stock', 'route' => 'inventory.stock.index', 'icon' => 'boxes'],
                ['label' => 'Receiving', 'route' => 'purchase.receipts.index', 'icon' => 'inbox'],
                ['label' => 'Picking', 'route' => 'delivery.picking.index', 'icon' => 'clipboard'],
            ],
            'Purchasing' => [
                ['label' => 'Home', 'route' => 'home.purchasing', 'icon' => 'home'],
                ['label' => 'Need', 'route' => 'purchase.reorder.index', 'icon' => 'sparkles'],
                ['label' => 'PO', 'route' => 'purchase.orders.index', 'icon' => 'document'],
                ['label' => 'Supplier', 'route' => 'suppliers.index', 'icon' => 'truck'],
            ],
            'Salesman' => [
                ['label' => 'Home', 'route' => 'home.salesman', 'icon' => 'home'],
                ['label' => 'Customer', 'route' => 'customers.index', 'icon' => 'users'],
                ['label' => 'Order', 'route' => 'sales.orders.index', 'icon' => 'receipt'],
                ['label' => 'Tagihan', 'route' => 'finance.receivables.index', 'icon' => 'wallet'],
            ],
            'Finance' => [
                ['label' => 'Home', 'route' => 'home.finance', 'icon' => 'home'],
                ['label' => 'Kas', 'route' => 'finance.cash.index', 'icon' => 'wallet'],
                ['label' => 'Piutang', 'route' => 'finance.receivables.index', 'icon' => 'arrow-down'],
                ['label' => 'Hutang', 'route' => 'finance.payables.index', 'icon' => 'arrow-up'],
            ],
            'Accounting' => [
                ['label' => 'Home', 'route' => 'home.accounting', 'icon' => 'home'],
                ['label' => 'Jurnal', 'route' => 'accounting.journals.index', 'icon' => 'book'],
                ['label' => 'Ledger', 'route' => 'accounting.ledger', 'icon' => 'list'],
                ['label' => 'Laporan', 'route' => 'accounting.reports.trial-balance', 'icon' => 'chart'],
            ],
        ];

        $default = [
            ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'home'],
            ['label' => 'Sales', 'route' => 'sales.orders.index', 'icon' => 'receipt'],
            ['label' => 'Stok', 'route' => 'inventory.stock.index', 'icon' => 'boxes'],
            ['label' => 'Keuangan', 'route' => 'finance.cash.index', 'icon' => 'wallet'],
        ];

        $items = $default;

        foreach ($sets as $role => $set) {
            if ($user?->hasRole($role)) {
                $items = $set;

                break;
            }
        }

        $items = array_values(array_filter($items, fn (array $item) => Route::has($item['route'])));
        $items = array_slice($items, 0, 4);

        $items[] = ['label' => 'Lainnya', 'route' => 'more', 'icon' => 'dots'];

        return $items;
    }

    /**
     * Tombol create global, isinya menyesuaikan role (PLAN 48).
     *
     * @return array<int, array{label: string, route: string, permission: string}>
     */
    public static function quickCreate(?User $user): array
    {
        $candidates = [
            ['label' => 'Order Penjualan', 'route' => 'sales.orders.create', 'permission' => 'sales.create'],
            ['label' => 'Customer', 'route' => 'customers.create', 'permission' => 'customer.create'],
            ['label' => 'Purchase Request', 'route' => 'purchase.requests.create', 'permission' => 'purchase.create'],
            ['label' => 'Purchase Order', 'route' => 'purchase.orders.create', 'permission' => 'purchase.create'],
            ['label' => 'Terima Barang', 'route' => 'purchase.receipts.create', 'permission' => 'inventory.receive'],
            ['label' => 'Transfer Stok', 'route' => 'inventory.transfers.create', 'permission' => 'inventory.transfer'],
            ['label' => 'Penyesuaian Stok', 'route' => 'inventory.adjustments.create', 'permission' => 'inventory.adjust'],
            ['label' => 'Terima Pembayaran', 'route' => 'finance.receipts.create', 'permission' => 'finance.receivable.collect'],
            ['label' => 'Bayar Hutang', 'route' => 'finance.payments.create', 'permission' => 'finance.payable.pay'],
            ['label' => 'Biaya', 'route' => 'finance.expenses.create', 'permission' => 'finance.expense.create'],
            ['label' => 'Produk', 'route' => 'products.create', 'permission' => 'product.create'],
            ['label' => 'User', 'route' => 'team.users.create', 'permission' => 'setting.user.manage'],
        ];

        return array_values(array_filter(
            $candidates,
            fn (array $item) => Route::has($item['route']) && $user?->can($item['permission']),
        ));
    }

    /**
     * @return array<int, array{label: string, icon: string, items: array<int, array{label: string, route: string, permission: ?string}>}>
     */
    private static function definition(): array
    {
        return [
            [
                'label' => 'Beranda',
                'icon' => 'home',
                'items' => [
                    ['label' => 'Dashboard', 'route' => 'dashboard', 'permission' => 'dashboard.view'],
                    ['label' => 'Tugas Saya', 'route' => 'tasks.index', 'permission' => 'dashboard.view'],
                ],
            ],
            [
                'label' => 'Penjualan',
                'icon' => 'receipt',
                'items' => [
                    ['label' => 'Kasir (POS)', 'route' => 'pos.index', 'permission' => 'pos.view'],
                    ['label' => 'Penawaran', 'route' => 'sales.quotations.index', 'permission' => 'sales.view'],
                    ['label' => 'Order Penjualan', 'route' => 'sales.orders.index', 'permission' => 'sales.view'],
                    ['label' => 'Picking', 'route' => 'delivery.picking.index', 'permission' => 'inventory.pick'],
                    ['label' => 'Pengiriman', 'route' => 'delivery.orders.index', 'permission' => 'delivery.view'],
                    ['label' => 'Invoice Penjualan', 'route' => 'sales.invoices.index', 'permission' => 'sales.view'],
                    ['label' => 'Retur Penjualan', 'route' => 'sales.returns.index', 'permission' => 'sales.return'],
                    ['label' => 'Shift Kasir', 'route' => 'pos.shift', 'permission' => 'pos.view'],
                ],
            ],
            [
                'label' => 'Barang & Stok',
                'icon' => 'boxes',
                'items' => [
                    ['label' => 'Produk', 'route' => 'products.index', 'permission' => 'product.view'],
                    ['label' => 'Kategori', 'route' => 'product-categories.index', 'permission' => 'product.view'],
                    ['label' => 'Brand', 'route' => 'brands.index', 'permission' => 'product.view'],
                    ['label' => 'Satuan', 'route' => 'units.index', 'permission' => 'product.view'],
                    ['label' => 'Aturan Harga', 'route' => 'price-rules.index', 'permission' => 'product.price.manage'],
                    ['label' => 'Stok', 'route' => 'inventory.stock.index', 'permission' => 'inventory.view'],
                    ['label' => 'Kartu Stok', 'route' => 'inventory.ledger.index', 'permission' => 'inventory.view'],
                    ['label' => 'Batch & Expiry', 'route' => 'inventory.batches.index', 'permission' => 'inventory.view'],
                    ['label' => 'Transfer Stok', 'route' => 'inventory.transfers.index', 'permission' => 'inventory.transfer'],
                    ['label' => 'Penyesuaian', 'route' => 'inventory.adjustments.index', 'permission' => 'inventory.adjust'],
                    ['label' => 'Stock Opname', 'route' => 'inventory.opnames.index', 'permission' => 'inventory.opname'],
                ],
            ],
            [
                'label' => 'Pembelian',
                'icon' => 'cart',
                'items' => [
                    ['label' => 'Rekomendasi Reorder', 'route' => 'purchase.reorder.index', 'permission' => 'purchase.view'],
                    ['label' => 'Purchase Request', 'route' => 'purchase.requests.index', 'permission' => 'purchase.view'],
                    ['label' => 'Purchase Order', 'route' => 'purchase.orders.index', 'permission' => 'purchase.view'],
                    ['label' => 'Penerimaan Barang', 'route' => 'purchase.receipts.index', 'permission' => 'inventory.receive'],
                    ['label' => 'Invoice Pembelian', 'route' => 'purchase.invoices.index', 'permission' => 'purchase.view'],
                    ['label' => 'Retur Pembelian', 'route' => 'purchase.returns.index', 'permission' => 'purchase.return'],
                ],
            ],
            [
                'label' => 'Customer',
                'icon' => 'users',
                'items' => [
                    ['label' => 'Daftar Customer', 'route' => 'customers.index', 'permission' => 'customer.view'],
                    ['label' => 'Grup Customer', 'route' => 'customer-groups.index', 'permission' => 'customer.view'],
                ],
            ],
            [
                'label' => 'Supplier',
                'icon' => 'truck',
                'items' => [
                    ['label' => 'Daftar Supplier', 'route' => 'suppliers.index', 'permission' => 'supplier.view'],
                    ['label' => 'Performa Supplier', 'route' => 'suppliers.performance', 'permission' => 'supplier.view'],
                ],
            ],
            [
                'label' => 'Keuangan',
                'icon' => 'wallet',
                'items' => [
                    ['label' => 'Kas & Bank', 'route' => 'finance.cash.index', 'permission' => 'finance.cash.view'],
                    ['label' => 'Piutang', 'route' => 'finance.receivables.index', 'permission' => 'finance.receivable.view'],
                    ['label' => 'Penerimaan', 'route' => 'finance.receipts.index', 'permission' => 'finance.receivable.collect'],
                    ['label' => 'Hutang', 'route' => 'finance.payables.index', 'permission' => 'finance.payable.view'],
                    ['label' => 'Pembayaran', 'route' => 'finance.payments.index', 'permission' => 'finance.payable.pay'],
                    ['label' => 'Biaya', 'route' => 'finance.expenses.index', 'permission' => 'finance.expense.view'],
                ],
            ],
            [
                'label' => 'Akuntansi',
                'icon' => 'book',
                'items' => [
                    ['label' => 'Chart of Accounts', 'route' => 'accounting.accounts.index', 'permission' => 'accounting.coa.view'],
                    ['label' => 'Jurnal', 'route' => 'accounting.journals.index', 'permission' => 'accounting.journal.view'],
                    ['label' => 'Buku Besar', 'route' => 'accounting.ledger', 'permission' => 'accounting.journal.view'],
                    ['label' => 'Neraca Saldo', 'route' => 'accounting.reports.trial-balance', 'permission' => 'accounting.report.view'],
                    ['label' => 'Laba Rugi', 'route' => 'accounting.reports.profit-loss', 'permission' => 'accounting.report.view'],
                    ['label' => 'Neraca', 'route' => 'accounting.reports.balance-sheet', 'permission' => 'accounting.report.view'],
                    ['label' => 'Arus Kas', 'route' => 'accounting.reports.cash-flow', 'permission' => 'accounting.report.view'],
                    ['label' => 'Periode Akuntansi', 'route' => 'accounting.periods.index', 'permission' => 'accounting.coa.view'],
                    ['label' => 'Aset Tetap', 'route' => 'assets.index', 'permission' => 'asset.view'],
                    ['label' => 'Payroll', 'route' => 'payroll.index', 'permission' => 'payroll.view'],
                ],
            ],
            [
                'label' => 'Insight',
                'icon' => 'sparkles',
                'items' => [
                    ['label' => 'Ringkasan Bisnis', 'route' => 'insight.index', 'permission' => 'report.profit'],
                    ['label' => 'Proyeksi Kas', 'route' => 'insight.cash-forecast', 'permission' => 'report.finance'],
                    ['label' => 'Pergerakan Stok', 'route' => 'insight.stock-movement', 'permission' => 'report.inventory'],
                    ['label' => 'Profitabilitas', 'route' => 'insight.profitability', 'permission' => 'report.profit'],
                ],
            ],
            [
                'label' => 'Laporan',
                'icon' => 'chart',
                'items' => [
                    ['label' => 'Penjualan', 'route' => 'reports.sales', 'permission' => 'report.sales'],
                    ['label' => 'Pembelian', 'route' => 'reports.purchase', 'permission' => 'report.purchase'],
                    ['label' => 'Nilai Persediaan', 'route' => 'reports.inventory-valuation', 'permission' => 'inventory.valuation.view'],
                    ['label' => 'Umur Piutang', 'route' => 'reports.ar-aging', 'permission' => 'finance.receivable.view'],
                    ['label' => 'Umur Hutang', 'route' => 'reports.ap-aging', 'permission' => 'finance.payable.view'],
                    ['label' => 'Laba per Cabang', 'route' => 'reports.profit-by-branch', 'permission' => 'report.profit'],
                    ['label' => 'Laba per Produk', 'route' => 'reports.profit-by-product', 'permission' => 'report.profit'],
                ],
            ],
            [
                'label' => 'Tim & Akses',
                'icon' => 'shield',
                'items' => [
                    ['label' => 'User', 'route' => 'team.users.index', 'permission' => 'setting.user.manage'],
                    ['label' => 'Role & Permission', 'route' => 'team.roles.index', 'permission' => 'setting.role.manage'],
                    ['label' => 'Aturan Approval', 'route' => 'approval.rules.index', 'permission' => 'approval.rule.manage'],
                    ['label' => 'Approval Saya', 'route' => 'approval.requests.index', 'permission' => 'approval.view'],
                    ['label' => 'Audit Trail', 'route' => 'team.audit.index', 'permission' => 'setting.audit.view'],
                ],
            ],
            [
                'label' => 'Pengaturan',
                'icon' => 'cog',
                'items' => [
                    ['label' => 'Company', 'route' => 'settings.company', 'permission' => 'setting.company.manage'],
                    ['label' => 'Cabang', 'route' => 'settings.branches.index', 'permission' => 'setting.branch.manage'],
                    ['label' => 'Gudang', 'route' => 'settings.warehouses.index', 'permission' => 'setting.branch.manage'],
                    ['label' => 'Kode Pajak', 'route' => 'settings.tax-codes.index', 'permission' => 'setting.company.manage'],
                    ['label' => 'Import Data', 'route' => 'settings.import.index', 'permission' => 'setting.import'],
                ],
            ],
        ];
    }

    private static function filterGroup(array $group, ?User $user): ?array
    {
        $items = array_values(array_filter(
            $group['items'],
            fn (array $item) => Route::has($item['route'])
                && ($item['permission'] === null || $user?->can($item['permission'])),
        ));

        if ($items === []) {
            return null;
        }

        return ['label' => $group['label'], 'icon' => $group['icon'], 'items' => $items];
    }
}
