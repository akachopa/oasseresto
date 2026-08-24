<?php

declare(strict_types=1);

namespace App\Modules\Auth\Support;

/**
 * Daftar tunggal seluruh permission dengan granularitas module.action (PLAN 10).
 * UI kustomisasi role membaca dari sini supaya user melihat permission per
 * modul, bukan ratusan checkbox datar.
 */
class PermissionRegistry
{
    /**
     * @return array<string, array{label: string, actions: array<string, string>}>
     */
    public static function modules(): array
    {
        return [
            'dashboard' => [
                'label' => 'Beranda',
                'actions' => [
                    'view' => 'Lihat dashboard',
                    'owner' => 'Lihat dashboard owner',
                    'financial' => 'Lihat angka keuangan',
                ],
            ],
            'sales' => [
                'label' => 'Penjualan',
                'actions' => [
                    'view' => 'Lihat penjualan',
                    'create' => 'Buat order',
                    'edit' => 'Ubah order',
                    'delete' => 'Hapus draft',
                    'approve' => 'Setujui order',
                    'post' => 'Posting invoice',
                    'cancel' => 'Batalkan order',
                    'return' => 'Retur penjualan',
                    'discount.override' => 'Override diskon',
                    'price.override' => 'Override harga',
                ],
            ],
            'pos' => [
                'label' => 'Kasir',
                'actions' => [
                    'view' => 'Buka kasir',
                    'sell' => 'Transaksi kasir',
                    'refund' => 'Refund',
                    'shift.open' => 'Buka shift',
                    'shift.close' => 'Tutup shift',
                ],
            ],
            'product' => [
                'label' => 'Barang',
                'actions' => [
                    'view' => 'Lihat produk',
                    'create' => 'Tambah produk',
                    'edit' => 'Ubah produk',
                    'delete' => 'Hapus produk',
                    'cost.view' => 'Lihat harga pokok',
                    'price.manage' => 'Kelola aturan harga',
                ],
            ],
            'inventory' => [
                'label' => 'Stok',
                'actions' => [
                    'view' => 'Lihat stok',
                    'receive' => 'Terima barang',
                    'pick' => 'Picking',
                    'transfer' => 'Transfer stok',
                    'adjust' => 'Penyesuaian stok',
                    'opname' => 'Stock opname',
                    'valuation.view' => 'Lihat nilai persediaan',
                ],
            ],
            'purchase' => [
                'label' => 'Pembelian',
                'actions' => [
                    'view' => 'Lihat pembelian',
                    'create' => 'Buat PR/PO',
                    'edit' => 'Ubah PR/PO',
                    'delete' => 'Hapus draft',
                    'approve' => 'Setujui PO',
                    'post' => 'Posting invoice pembelian',
                    'return' => 'Retur pembelian',
                ],
            ],
            'customer' => [
                'label' => 'Customer',
                'actions' => [
                    'view' => 'Lihat customer',
                    'create' => 'Tambah customer',
                    'edit' => 'Ubah customer',
                    'delete' => 'Hapus customer',
                    'credit.manage' => 'Kelola credit limit',
                    'credit.override' => 'Override credit limit',
                ],
            ],
            'supplier' => [
                'label' => 'Supplier',
                'actions' => [
                    'view' => 'Lihat supplier',
                    'create' => 'Tambah supplier',
                    'edit' => 'Ubah supplier',
                    'delete' => 'Hapus supplier',
                ],
            ],
            'delivery' => [
                'label' => 'Pengiriman',
                'actions' => [
                    'view' => 'Lihat pengiriman',
                    'create' => 'Buat surat jalan',
                    'dispatch' => 'Kirim',
                    'complete' => 'Selesaikan pengiriman',
                ],
            ],
            'finance' => [
                'label' => 'Keuangan',
                'actions' => [
                    'view' => 'Lihat keuangan',
                    'cash.view' => 'Lihat kas & bank',
                    'cash.manage' => 'Kelola kas & bank',
                    'receivable.view' => 'Lihat piutang',
                    'receivable.collect' => 'Terima pembayaran',
                    'receivable.writeoff' => 'Hapus buku piutang',
                    'payable.view' => 'Lihat hutang',
                    'payable.pay' => 'Bayar hutang',
                    'expense.view' => 'Lihat biaya',
                    'expense.create' => 'Input biaya',
                    'expense.approve' => 'Setujui biaya',
                    'expense.post' => 'Bayarkan biaya',
                ],
            ],
            'accounting' => [
                'label' => 'Akuntansi',
                'actions' => [
                    'coa.view' => 'Lihat COA',
                    'coa.manage' => 'Kelola COA',
                    'journal.view' => 'Lihat jurnal',
                    'journal.create' => 'Buat jurnal manual',
                    'journal.post' => 'Posting jurnal',
                    'journal.reverse' => 'Balik jurnal',
                    'period.close' => 'Tutup periode',
                    'period.reopen' => 'Buka kembali periode',
                    'report.view' => 'Lihat laporan akuntansi',
                ],
            ],
            'asset' => [
                'label' => 'Aset',
                'actions' => [
                    'view' => 'Lihat aset',
                    'create' => 'Tambah aset',
                    'edit' => 'Ubah aset',
                    'depreciate' => 'Jalankan depresiasi',
                ],
            ],
            'payroll' => [
                'label' => 'Payroll',
                'actions' => [
                    'view' => 'Lihat payroll',
                    'create' => 'Buat payroll',
                    'approve' => 'Setujui payroll',
                    'post' => 'Posting payroll',
                ],
            ],
            'report' => [
                'label' => 'Laporan',
                'actions' => [
                    'sales' => 'Laporan penjualan',
                    'purchase' => 'Laporan pembelian',
                    'inventory' => 'Laporan stok',
                    'finance' => 'Laporan keuangan',
                    'profit' => 'Laporan laba',
                    'export' => 'Export laporan',
                ],
            ],
            'approval' => [
                'label' => 'Approval',
                'actions' => [
                    'view' => 'Lihat approval',
                    'act' => 'Setujui / tolak',
                    'rule.manage' => 'Kelola aturan approval',
                ],
            ],
            'setting' => [
                'label' => 'Pengaturan',
                'actions' => [
                    'view' => 'Lihat pengaturan',
                    'company.manage' => 'Kelola company',
                    'branch.manage' => 'Kelola cabang & gudang',
                    'user.manage' => 'Kelola user',
                    'role.manage' => 'Kelola role & permission',
                    'import' => 'Import data',
                    'audit.view' => 'Lihat audit trail',
                ],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        $permissions = [];

        foreach (self::modules() as $module => $definition) {
            foreach (array_keys($definition['actions']) as $action) {
                $permissions[] = "{$module}.{$action}";
            }
        }

        return $permissions;
    }

    public static function label(string $permission): string
    {
        [$module, $action] = array_pad(explode('.', $permission, 2), 2, '');

        return self::modules()[$module]['actions'][$action] ?? $permission;
    }
}
