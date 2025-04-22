<?php

return [
    'environment' => env('PHONEPE_ENV', 'uat'),
    'redirect_url' => env('PHONEPE_REDIRECT_URL', 'http://127.0.0.1:8000/payment/verify'),
    'uat' => [
        'client_id' => env('PHONEPE_UAT_CLIENT_ID', 'TEST-M22ZNK7QX3FS6_25041'),
        'client_version' => env('PHONEPE_UAT_CLIENT_VERSION', 1),
        'client_secret' => env('PHONEPE_UAT_CLIENT_SECRET', 'ZmEzZWU4MmQtMWIxOC00NTA4LWE2YzMtNjc5YjQ4YzlkY2Nl'),
        'base_url' => 'https://api-preprod.phonepe.com/apis/pg-sandbox',
        'auth_url' => 'https://api-preprod.phonepe.com/apis/pg-sandbox',
        'token_cache_path' => storage_path('app/phonepe/phonepe_token_uat.json'),
    ],

    'prod' => [
        'client_id' => env('PHONEPE_PROD_CLIENT_ID', ''),
        'client_version' => env('PHONEPE_PROD_CLIENT_VERSION', ''),
        'client_secret' => env('PHONEPE_PROD_CLIENT_SECRET', ''),
        'base_url' => 'https://api.phonepe.com/apis/pg',
        'auth_url' => 'https://api.phonepe.com/apis/identity-manager',
        'token_cache_path' => storage_path('app/phonepe/phonepe_token_prod.json'),
    ],
];
?>
