<?php

return [

    'app_id' => env('TELEBIRR_APP_ID'),

    'app_key' => env('TELEBIRR_APP_KEY'),

    'short_code' => env('TELEBIRR_SHORT_CODE'),

    'public_key' => env('TELEBIRR_PUBLIC_KEY'),

    'api_url' => env('TELEBIRR_API_URL', 'https://app.ethiomobilemoney.et:2121/ammapi/payment/service-openup/toTradeWebPay'),

    'notify_url' => env('TELEBIRR_NOTIFY_URL'),

    'return_url' => env('TELEBIRR_RETURN_URL'),

    'timeout_url' => env('TELEBIRR_TIMEOUT_URL'),

];
