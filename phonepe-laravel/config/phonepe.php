<?php

return [
    'environment' => env('PHONEPE_ENV', 'uat'),

    'uat' => [
        'client_id' => env('PHONEPE_UAT_CLIENT_ID', 'SU2504041946024365502022'),
        'client_version' => env('PHONEPE_UAT_CLIENT_VERSION', 1),
        'client_secret' => env('PHONEPE_UAT_CLIENT_SECRET', 'ae83cba2-07c0-43a9-b0c4-1d84c261fd12'),
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