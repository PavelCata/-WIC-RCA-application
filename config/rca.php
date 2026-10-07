<?php

return [
    'base_url' => env('RCA_API_BASE_URL', 'https://rca-qa.api.lifeishard.ro'),
    'account' => env('RCA_API_ACCOUNT'),
    'password' => env('RCA_API_PASSWORD'),
    'provider_account' => env('RCA_PROVIDER_ACCOUNT'),
    'provider_password' => env('RCA_PROVIDER_PASSWORD'),
    'provider_code' => env('RCA_PROVIDER_CODE'),
    'timeout' => env('RCA_API_TIMEOUT', 15),
    'connect_timeout' => env('RCA_API_CONNECT_TIMEOUT', 5),
    'verify_ssl' => env('RCA_API_VERIFY_SSL', true),
    'ca_bundle' => env('RCA_API_CA_BUNDLE'),
];
