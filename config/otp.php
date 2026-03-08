<?php

return [
    'length' => (int) env('OTP_LENGTH', 6),
    'expiry_minutes' => (int) env('OTP_EXPIRY_MINUTES', 5),
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
];
