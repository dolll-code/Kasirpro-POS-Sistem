<?php

return [
    /*
    |--------------------------------------------------------------------------
    | POS & Cashier Discount Limits (PRD §4.2, §5.6, §6.4)
    |--------------------------------------------------------------------------
    |
    | Limits for cashiers who only possess 'sales.discount.within_limit' permission.
    | Any discount exceeding these limits strictly requires maker-checker
    | approval by a user with 'sales.discount.override' permission.
    |
    */
    'discount_limits' => [
        'max_cashier_item_discount_percent' => (float) env('POS_MAX_ITEM_DISCOUNT_PERCENT', 10.0),
        'max_cashier_item_discount_amount' => (float) env('POS_MAX_ITEM_DISCOUNT_AMOUNT', 50000.00),
        'max_cashier_transaction_discount_percent' => (float) env('POS_MAX_TX_DISCOUNT_PERCENT', 10.0),
        'max_cashier_transaction_discount_amount' => (float) env('POS_MAX_TX_DISCOUNT_AMOUNT', 100000.00),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Cash Account Code for POS Checkout
    |--------------------------------------------------------------------------
    */
    'default_cash_account_code' => env('POS_DEFAULT_CASH_ACCOUNT_CODE', 'KAS-TOKO'),
];
