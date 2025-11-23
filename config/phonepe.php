<?php

return [
    'environment' => env('PHONEPE_ENV', 'uat'),

    'uat' => [
        'client_id' => env('PHONEPE_UAT_CLIENT_ID'),
        'client_version' => env('PHONEPE_CLIENT_VERSION', '1.0'),
        'client_secret' => env('PHONEPE_UAT_CLIENT_SECRET'),
        'base_url' => 'https://api-uat.phonepe.com',
        'auth_url' => 'https://api-uat.phonepe.com',
        'merchant_id' => env('PHONEPE_UAT_MERCHANT_ID'),
    ],

    'prod' => [
        'client_id' => env('PHONEPE_PROD_CLIENT_ID'),
        'client_version' => env('PHONEPE_CLIENT_VERSION', '1.0'),
        'client_secret' => env('PHONEPE_PROD_CLIENT_SECRET'),
        'base_url' => 'https://api.phonepe.com',
        'auth_url' => 'https://api.phonepe.com',
        'merchant_id' => env('PHONEPE_PROD_MERCHANT_ID'),
    ],

    'redirect_url' => env('PHONEPE_REDIRECT_URL'),
    'webhook_salt_key' => env('PHONEPE_WEBHOOK_SALT_KEY'),
    'webhook_salt_index' => env('PHONEPE_WEBHOOK_SALT_INDEX', 1),
    'payment_mode' => env('PHONEPE_PAYMENT_MODE', 'iframe'), // 'iframe' or 'redirect'
];
