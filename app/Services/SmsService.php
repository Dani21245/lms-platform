<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function send(string $phone, string $message): bool
    {
        $provider = config('sms.provider');

        try {
            return match ($provider) {
                'africastalking' => $this->sendViaAfricasTalking($phone, $message),
                default => $this->logSms($phone, $message),
            };
        } catch (\Exception $e) {
            Log::error('SMS sending failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function sendOtp(string $phone, string $code): bool
    {
        $message = "Your LMS Platform verification code is: {$code}. Valid for ".config('sms.otp_expiry_minutes').' minutes.';

        return $this->send($phone, $message);
    }

    private function sendViaAfricasTalking(string $phone, string $message): bool
    {
        $response = Http::withHeaders([
            'apiKey' => config('sms.api_key'),
            'Accept' => 'application/json',
        ])->post('https://api.africastalking.com/version1/messaging', [
            'username' => config('sms.username', 'sandbox'),
            'to' => $phone,
            'message' => $message,
            'from' => config('sms.sender_id'),
        ]);

        if ($response->successful()) {
            Log::info('SMS sent successfully', ['phone' => $phone]);

            return true;
        }

        Log::error('SMS API error', [
            'phone' => $phone,
            'response' => $response->body(),
        ]);

        return false;
    }

    private function logSms(string $phone, string $message): bool
    {
        Log::info('SMS (logged)', [
            'phone' => $phone,
            'message' => $message,
        ]);

        return true;
    }
}
