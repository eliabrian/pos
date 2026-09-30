<?php

return [
    'doku' => [
        'production' => env('DOKU_PRODUCTION_ENDPOINT', 'https://api.doku.com'),
        'sandbox' => env('DOKU_SANDBOX_ENDPOINT', 'https://api-sandbox.doku.com'),
        'paths' => [
            'check_status' => '/orders/v1/status',
            'doku_checkout' => '/checkout/v1/payment',
        ],
    ],
];
