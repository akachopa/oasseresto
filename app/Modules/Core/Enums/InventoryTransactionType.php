<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

enum InventoryTransactionType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case PurchaseReturn = 'purchase_return';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case StockOpname = 'stock_opname';
    case Damaged = 'damaged';
    case Expired = 'expired';
    case OpeningBalance = 'opening_balance';

    /**
     * Arah pergerakan stok: 1 menambah, -1 mengurangi.
     */
    public function direction(): int
    {
        return match ($this) {
            self::Purchase,
            self::SaleReturn,
            self::TransferIn,
            self::AdjustmentIn,
            self::OpeningBalance => 1,
            self::Sale,
            self::PurchaseReturn,
            self::TransferOut,
            self::AdjustmentOut,
            self::Damaged,
            self::Expired => -1,
            self::StockOpname => 0,
        };
    }

    public function isInbound(): bool
    {
        return $this->direction() === 1;
    }

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Pembelian',
            self::Sale => 'Penjualan',
            self::SaleReturn => 'Retur Penjualan',
            self::PurchaseReturn => 'Retur Pembelian',
            self::TransferIn => 'Transfer Masuk',
            self::TransferOut => 'Transfer Keluar',
            self::AdjustmentIn => 'Penyesuaian Masuk',
            self::AdjustmentOut => 'Penyesuaian Keluar',
            self::StockOpname => 'Stock Opname',
            self::Damaged => 'Barang Rusak',
            self::Expired => 'Barang Kedaluwarsa',
            self::OpeningBalance => 'Saldo Awal',
        };
    }
}
