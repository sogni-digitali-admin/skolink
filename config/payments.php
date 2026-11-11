<?php

return [

    /*
     * Default driver when a school has no active row in payment_gateways.
     * Per-school driver + credentials live in the payment_gateways table,
     * never in code.
     */
    'default' => env('PAYMENTS_DEFAULT_DRIVER', 'manual'),

    /*
     * Fallback ISO currency when config('school.currency') is unavailable.
     * Amounts are integer cents of this currency end-to-end.
     */
    'currency_fallback' => 'MUR',

    'gateways' => [

        // Record-only rails: cash, cheque, bank transfer, Juice by reference.
        'manual' => [
            'methods' => ['cash', 'cheque', 'bank_transfer', 'juice_reference'],
        ],

        // BETA: structural skeleton, disabled until real credentials exist.
        'mips' => [
            'enabled'             => env('MIPS_ENABLED', false),
            'mode'                => env('MIPS_MODE', 'sandbox'),
            'merchant_id'         => env('MIPS_MERCHANT_ID'),
            'merchant_secret'     => env('MIPS_MERCHANT_SECRET'),
            'webhook_secret'      => env('MIPS_WEBHOOK_SECRET'),
            'base_url_sandbox'    => env('MIPS_BASE_URL_SANDBOX'),
            'base_url_production' => env('MIPS_BASE_URL_PRODUCTION'),
            'signature_header'    => env('MIPS_SIGNATURE_HEADER', 'X-MIPS-Signature'),
            'timeout'             => (int) env('MIPS_TIMEOUT', 15),
        ],
    ],

];
