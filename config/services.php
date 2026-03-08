<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'sms' => [
        'provider' => env('SMS_PROVIDER', 'log'),
        'api_key' => env('SMS_API_KEY'),
        'api_secret' => env('SMS_API_SECRET'),
        'sender_id' => env('SMS_SENDER_ID', 'LMS'),
    ],

    'telebirr' => [
        'app_id' => env('TELEBIRR_APP_ID'),
        'app_key' => env('TELEBIRR_APP_KEY'),
        'short_code' => env('TELEBIRR_SHORT_CODE'),
        'public_key' => env('TELEBIRR_PUBLIC_KEY'),
        'notify_url' => env('TELEBIRR_NOTIFY_URL'),
        'return_url' => env('TELEBIRR_RETURN_URL'),
        'timeout_url' => env('TELEBIRR_TIMEOUT_URL'),
        'api_url' => env('TELEBIRR_API_URL', 'https://app.ethiomobilemoney.et:2121'),
    ],

];
