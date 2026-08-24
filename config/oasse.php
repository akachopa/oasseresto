<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Brand
    |---------------------------------------------------------------------------
    */
    'brand' => [
        'name' => 'OASSE',
        'tagline' => 'Wholesale Distribution & Accounting OS',
    ],

    /*
    |---------------------------------------------------------------------------
    | Inventory
    |---------------------------------------------------------------------------
    |
    | Nilai di sini adalah default company. Setiap company dapat menimpanya
    | lewat kolom settings (JSONB) pada tabel companies.
    |
    */
    'inventory' => [
        'allow_negative_stock' => (bool) env('OASSE_ALLOW_NEGATIVE_STOCK', false),
        'costing_method' => env('OASSE_COSTING_METHOD', 'weighted_average'),
        'physical_strategy' => 'fefo',
        'near_expiry_days' => 60,
        'dead_stock_days' => 90,
    ],

    /*
    |---------------------------------------------------------------------------
    | Credit control
    |---------------------------------------------------------------------------
    |
    | mode: block | warn | approval
    |
    */
    'credit' => [
        'mode' => env('OASSE_CREDIT_LIMIT_MODE', 'approval'),
        'aging_buckets' => [30, 60, 90],

        /*
         * Diskon pelunasan dini: persen potongan bila tagihan dibayar dalam
         * tenggang hari sejak tanggal invoice dan belum jatuh tempo.
         */
        'early_payment' => [
            'percent' => (float) env('OASSE_EARLY_PAYMENT_PERCENT', 2),
            'within_days' => (int) env('OASSE_EARLY_PAYMENT_DAYS', 10),
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Pricing
    |---------------------------------------------------------------------------
    */
    'pricing' => [
        'min_margin_percent' => (float) env('OASSE_MIN_MARGIN_PERCENT', 5),
        'margin_guard' => 'approval',
    ],

    /*
    |---------------------------------------------------------------------------
    | Document numbering
    |---------------------------------------------------------------------------
    |
    | Pola: {PREFIX}/{BRANCH}/{YYYY}/{MM}/{SEQ}
    |
    */
    'numbering' => [
        'pattern' => '{prefix}/{branch}/{year}/{month}/{seq}',
        'sequence_length' => 5,
        'prefix' => [
            'purchase_request' => 'PR',
            'purchase_order' => 'PO',
            'goods_receipt' => 'GR',
            'purchase_invoice' => 'PI',
            'purchase_return' => 'PRT',
            'quotation' => 'QT',
            'sales_order' => 'SO',
            'delivery' => 'DO',
            'sales_invoice' => 'INV',
            'sales_return' => 'SRT',
            'stock_transfer' => 'TRF',
            'stock_adjustment' => 'ADJ',
            'stock_opname' => 'OPN',
            'receipt' => 'RCP',
            'payment' => 'PAY',
            'expense' => 'EXP',
            'journal_entry' => 'JV',
            'fixed_asset' => 'FA',
            'payroll' => 'PYR',
            'pos_sale' => 'POS',
            'cashier_shift' => 'SHF',
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Accounting
    |---------------------------------------------------------------------------
    */
    'accounting' => [
        'auto_post' => true,
        'currency' => 'IDR',
        'decimal' => 2,
    ],

    /*
    |---------------------------------------------------------------------------
    | Dashboard
    |---------------------------------------------------------------------------
    */
    'dashboard' => [
        'cache_ttl' => 300,
        'forecast_horizons' => [7, 14, 30],
    ],

    /*
    |---------------------------------------------------------------------------
    | Alerts
    |---------------------------------------------------------------------------
    */
    'alerts' => [
        'cash_low' => (float) env('OASSE_ALERT_CASH_LOW', 1_000_000),
    ],
];
