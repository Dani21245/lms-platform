<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function send(string $phone, string $message): bool
    {
        $provider = config('services.sms.provider', 'log');

        return match ($provider) {
            'africastalking' => $this->sendViaAfricasTalking($phone, $message),
            default => $this->logSms($phone, $message),
        };
    }

    protected function sendViaAfricasTalking(string $phone, string $message): bool
    {
        try {
            $response = Http::withHeaders([
                'apiKey' => config('services.sms.api_key'),
                'Accept' => 'application/json',
            ])->post('https://api.africastalking.com/version1/messaging', [
                'username' => config('services.sms.api_secret'),
                'to' => $phone,
                'message' => $message,
                'from' => config('services.sms.sender_id'),
            ]);

            if ($response->successful()) {
                Log::info('SMS sent successfully', ['phone' => $phone]);
                return true;
            }

            Log::error('SMS sending failed', [
                'phone' => $phone,
                'response' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('SMS sending exception', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function logSms(string $phone, string $message): bool
    {
        Log::info('SMS (log driver)', [
            'phone' => $phone,
            'message' => $message,
        ]);

        return true;
    }
}
