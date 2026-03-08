<?php

return [

    'provider' => env('SMS_PROVIDER', 'africastalking'),

    'api_key' => env('SMS_API_KEY'),

    'api_secret' => env('SMS_API_SECRET'),

    'sender_id' => env('SMS_SENDER_ID', 'LMSPlatform'),

    'otp_length' => (int) env('OTP_LENGTH', 6),

    'otp_expiry_minutes' => (int) env('OTP_EXPIRY_MINUTES', 5),

];
