<?php

declare(strict_types=1);

namespace App\Modules\Auth\Support;

/**
 * Template role siap pakai (PLAN 9). Owner memilih template lalu boleh
 * melakukan kustomisasi permission per modul.
 */
class RoleTemplate
{
    /**
     * Nilai '*' berarti seluruh permission modul tersebut.
     *
     * @return array<string, array{description: string, permissions: array<int, string>}>
     */
    public static function all(): array
    {
        return [
            'Owner' => [
                'description' => 'Akses penuh ke seluruh modul dan seluruh cabang.',
                'permissions' => ['*'],
            ],
            'General Manager' => [
                'description' => 'Operasional dan keuangan penuh, tanpa kelola user dan tutup buku.',
                'permissions' => [
                    'dashboard.*', 'sales.*', 'product.*', 'inventory.*', 'purchase.*',
                    'customer.*', 'supplier.*', 'delivery.*', 'finance.*', 'report.*',
                    'approval.view', 'approval.act', 'accounting.report.view', 'accounting.journal.view',
                    'asset.view', 'payroll.view', 'setting.view',
                ],
            ],
            'Branch Manager' => [
                'description' => 'Operasional satu cabang termasuk approval level menengah.',
                'permissions' => [
                    'dashboard.view', 'dashboard.financial',
                    'sales.view', 'sales.create', 'sales.edit', 'sales.approve', 'sales.post',
                    'sales.cancel', 'sales.return', 'sales.discount.override', 'sales.price.override',
                    'product.view', 'product.cost.view',
                    'inventory.view', 'inventory.receive', 'inventory.pick', 'inventory.transfer',
                    'inventory.adjust', 'inventory.opname', 'inventory.valuation.view',
                    'purchase.view', 'purchase.create', 'purchase.approve',
                    'customer.view', 'customer.create', 'customer.edit', 'customer.credit.manage',
                    'supplier.view', 'delivery.*',
                    'finance.view', 'finance.cash.view', 'finance.receivable.view',
                    'finance.receivable.collect', 'finance.payable.view', 'finance.expense.view',
                    'finance.expense.create', 'finance.expense.approve',
                    'report.sales', 'report.purchase', 'report.inventory', 'report.profit', 'report.export',
                    'approval.view', 'approval.act',
                ],
            ],
            'Cashier' => [
                'description' => 'Hanya kasir, shift, dan customer dasar.',
                'permissions' => [
                    'dashboard.view',
                    'pos.*',
                    'sales.view', 'sales.create',
                    'product.view',
                    'customer.view', 'customer.create',
                    'finance.receivable.collect',
                ],
            ],
            'Salesman' => [
                'description' => 'Order, kunjungan, dan penagihan customer miliknya.',
                'permissions' => [
                    'dashboard.view',
                    'sales.view', 'sales.create', 'sales.edit',
                    'product.view',
                    'customer.view', 'customer.create', 'customer.edit',
                    'inventory.view',
                    'finance.receivable.view', 'finance.receivable.collect',
                    'report.sales',
                ],
            ],
            'Sales Supervisor' => [
                'description' => 'Supervisi salesman, approval diskon dan harga.',
                'permissions' => [
                    'dashboard.view', 'dashboard.financial',
                    'sales.*', 'product.view', 'product.cost.view', 'product.price.manage',
                    'customer.*', 'inventory.view',
                    'finance.receivable.view', 'finance.receivable.collect',
                    'report.sales', 'report.profit', 'report.export',
                    'approval.view', 'approval.act',
                ],
            ],
            'Warehouse Staff' => [
                'description' => 'Receiving, picking, dan transfer di gudang yang diberikan.',
                'permissions' => [
                    'dashboard.view',
                    'product.view',
                    'inventory.view', 'inventory.receive', 'inventory.pick', 'inventory.transfer',
                    'delivery.view', 'delivery.create',
                    'purchase.view',
                    'sales.view',
                ],
            ],
            'Warehouse Supervisor' => [
                'description' => 'Seluruh aktivitas gudang termasuk adjustment dan opname.',
                'permissions' => [
                    'dashboard.view',
                    'product.view', 'product.cost.view',
                    'inventory.*', 'delivery.*',
                    'purchase.view', 'sales.view',
                    'report.inventory', 'report.export',
                    'approval.view', 'approval.act',
                ],
            ],
            'Purchasing' => [
                'description' => 'Reorder, PR, PO, dan pengelolaan supplier.',
                'permissions' => [
                    'dashboard.view',
                    'product.view', 'product.cost.view', 'product.create', 'product.edit',
                    'inventory.view',
                    'purchase.view', 'purchase.create', 'purchase.edit', 'purchase.delete', 'purchase.return',
                    'supplier.*',
                    'report.purchase', 'report.inventory', 'report.export',
                ],
            ],
            'Finance' => [
                'description' => 'Kas, bank, piutang, hutang, dan biaya.',
                'permissions' => [
                    'dashboard.view', 'dashboard.financial',
                    'sales.view', 'purchase.view',
                    'customer.view', 'customer.credit.manage', 'supplier.view',
                    'product.view',
                    'finance.*',
                    'asset.view', 'asset.create', 'asset.edit',
                    'payroll.view',
                    'report.finance', 'report.sales', 'report.purchase', 'report.export',
                    'approval.view', 'approval.act',
                ],
            ],
            'Accounting' => [
                'description' => 'Jurnal, buku besar, dan laporan keuangan.',
                'permissions' => [
                    'dashboard.view', 'dashboard.financial',
                    'sales.view', 'purchase.view', 'product.view', 'product.cost.view',
                    'inventory.view', 'inventory.valuation.view',
                    'customer.view', 'supplier.view',
                    'finance.view', 'finance.cash.view', 'finance.receivable.view',
                    'finance.payable.view', 'finance.expense.view',
                    'accounting.*',
                    'asset.view', 'asset.depreciate',
                    'payroll.view', 'payroll.post',
                    'report.*',
                    'setting.audit.view',
                ],
            ],
            'Auditor' => [
                'description' => 'Read-only ke seluruh data termasuk audit trail.',
                'permissions' => [
                    'dashboard.view', 'dashboard.financial',
                    'sales.view', 'purchase.view', 'product.view', 'product.cost.view',
                    'inventory.view', 'inventory.valuation.view',
                    'customer.view', 'supplier.view', 'delivery.view',
                    'finance.view', 'finance.cash.view', 'finance.receivable.view',
                    'finance.payable.view', 'finance.expense.view',
                    'accounting.coa.view', 'accounting.journal.view', 'accounting.report.view',
                    'asset.view', 'payroll.view',
                    'report.*',
                    'setting.audit.view',
                ],
            ],
            'Viewer' => [
                'description' => 'Read-only operasional tanpa data biaya dan margin.',
                'permissions' => [
                    'dashboard.view',
                    'sales.view', 'purchase.view', 'product.view',
                    'inventory.view', 'customer.view', 'supplier.view', 'delivery.view',
                ],
            ],
        ];
    }

    /**
     * Kembangkan pola wildcard menjadi daftar permission konkret.
     *
     * @return array<int, string>
     */
    public static function resolve(string $role): array
    {
        $patterns = self::all()[$role]['permissions'] ?? [];
        $available = PermissionRegistry::all();
        $resolved = [];

        foreach ($patterns as $pattern) {
            if ($pattern === '*') {
                return $available;
            }

            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -1);
                $resolved = array_merge(
                    $resolved,
                    array_filter($available, fn (string $p) => str_starts_with($p, $prefix)),
                );

                continue;
            }

            if (in_array($pattern, $available, true)) {
                $resolved[] = $pattern;
            }
        }

        return array_values(array_unique($resolved));
    }

    /**
     * Role yang secara default diberi scope seluruh company.
     *
     * @return array<int, string>
     */
    public static function companyWide(): array
    {
        return ['Owner', 'General Manager', 'Finance', 'Accounting', 'Auditor', 'Purchasing'];
    }
}
