<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bakong Open API (NBC KHQR)
    |--------------------------------------------------------------------------
    |
    | Credentials live in .env — never hard-code the token here.
    |
    | KHQR strings are generated locally with the KHQR SDK
    | (fidele007/bakong-khqr-php); the Open API is only used to verify
    | transactions (POST /v1/check_transaction_by_md5).
    |
    */

    // Bearer token obtained from the Bakong developer portal.
    'token' => env('BAKONG_API_TOKEN'),

    // Bakong account that receives the money, e.g. "tinhshop@aclb".
    'account_id' => env('BAKONG_ACCOUNT_ID'),

    // Merchant details embedded in the generated KHQR.
    'merchant_name' => env('BAKONG_MERCHANT_NAME', 'TINH SHOP'),
    'merchant_city' => env('BAKONG_MERCHANT_CITY', 'PHNOM PENH'),

    // Default settlement currency for KHQR payments: 'USD' or 'KHR'.
    // (The customer can still switch between the two on the payment screen.)
    'currency' => env('BAKONG_CURRENCY', 'USD'),

    // Exchange rate used when the customer chooses to pay in Riel:
    // 1 USD = X KHR. Keep it up to date with the NBC reference rate.
    'usd_khr_rate' => (float) env('BAKONG_USD_KHR_RATE', 4100),

    // How long a generated QR stays valid (minutes) before it expires.
    'qr_expiry_minutes' => (int) env('BAKONG_QR_EXPIRY_MINUTES', 15),

    // true = use the Bakong SIT (test) environment instead of production.
    'test_mode' => (bool) env('BAKONG_TEST_MODE', false),

];