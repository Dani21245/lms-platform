<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\TransactionLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelebirrService
{
    public function initiatePayment(Payment $payment): array
    {
        $timestamp = now()->format('YmdHis');
        $nonce = Str::random(32);
        $outTradeNo = $payment->transaction_ref;

        $params = [
            'appId' => config('telebirr.app_id'),
            'notifyUrl' => config('telebirr.notify_url'),
            'returnUrl' => config('telebirr.return_url'),
            'outTradeNo' => $outTradeNo,
            'subject' => 'Course Payment - '.$payment->course->title,
            'shortCode' => config('telebirr.short_code'),
            'timeoutExpress' => '30',
            'totalAmount' => number_format((float) $payment->amount, 2, '.', ''),
            'nonce' => $nonce,
            'timestamp' => $timestamp,
            'receiveName' => 'LMS Platform',
        ];

        // Sign the payload
        ksort($params);
        $stringA = collect($params)
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode('&');

        $signedPayload = $this->signPayload($stringA);

        $requestBody = [
            'appid' => config('telebirr.app_id'),
            'sign' => $signedPayload,
            'ussd' => $this->encryptPayload(json_encode($params)),
        ];

        // Log the request
        TransactionLog::create([
            'payment_id' => $payment->id,
            'transaction_ref' => $outTradeNo,
            'event_type' => 'payment_initiation',
            'status' => 'pending',
            'request_data' => $params,
        ]);

        try {
            $response = Http::post(config('telebirr.api_url'), $requestBody);

            $responseData = $response->json();

            TransactionLog::create([
                'payment_id' => $payment->id,
                'transaction_ref' => $outTradeNo,
                'event_type' => 'payment_initiation_response',
                'status' => $response->successful() ? 'success' : 'failed',
                'response_data' => $responseData,
            ]);

            if ($response->successful() && isset($responseData['data']['toPayUrl'])) {
                return [
                    'success' => true,
                    'payment_url' => $responseData['data']['toPayUrl'],
                    'transaction_ref' => $outTradeNo,
                ];
            }

            Log::error('Telebirr payment initiation failed', [
                'response' => $responseData,
                'payment_id' => $payment->id,
            ]);

            return [
                'success' => false,
                'message' => $responseData['msg'] ?? 'Payment initiation failed',
            ];
        } catch (\Exception $e) {
            Log::error('Telebirr API error', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
            ]);

            TransactionLog::create([
                'payment_id' => $payment->id,
                'transaction_ref' => $outTradeNo,
                'event_type' => 'payment_initiation_error',
                'status' => 'error',
                'response_data' => ['error' => $e->getMessage()],
            ]);

            return [
                'success' => false,
                'message' => 'Payment service unavailable. Please try again.',
            ];
        }
    }

    public function verifyWebhook(array $data): array
    {
        try {
            $decryptedData = $this->decryptPayload($data['notification'] ?? '');
            $payload = json_decode($decryptedData, true);

            if (! $payload) {
                return ['valid' => false, 'message' => 'Invalid payload'];
            }

            return [
                'valid' => true,
                'out_trade_no' => $payload['outTradeNo'] ?? null,
                'trade_no' => $payload['tradeNo'] ?? null,
                'total_amount' => $payload['totalAmount'] ?? null,
                'transaction_status' => $payload['tradeStatus'] ?? null,
                'raw' => $payload,
            ];
        } catch (\Exception $e) {
            Log::error('Webhook verification failed', [
                'error' => $e->getMessage(),
            ]);

            return ['valid' => false, 'message' => $e->getMessage()];
        }
    }

    private function signPayload(string $data): string
    {
        $appKey = config('telebirr.app_key');

        return strtoupper(hash('sha256', $data.'&key='.$appKey));
    }

    private function encryptPayload(string $data): string
    {
        $publicKey = config('telebirr.public_key');
        $key = "-----BEGIN PUBLIC KEY-----\n".wordwrap($publicKey, 64, "\n", true)."\n-----END PUBLIC KEY-----";

        $encrypted = '';
        $dataChunks = str_split($data, 117);

        foreach ($dataChunks as $chunk) {
            $encryptedChunk = '';
            openssl_public_encrypt($chunk, $encryptedChunk, $key);
            $encrypted .= $encryptedChunk;
        }

        return base64_encode($encrypted);
    }

    private function decryptPayload(string $encryptedData): string
    {
        $publicKey = config('telebirr.public_key');
        $key = "-----BEGIN PUBLIC KEY-----\n".wordwrap($publicKey, 64, "\n", true)."\n-----END PUBLIC KEY-----";

        $data = base64_decode($encryptedData);
        $decrypted = '';
        $dataChunks = str_split($data, 128);

        foreach ($dataChunks as $chunk) {
            $decryptedChunk = '';
            openssl_public_decrypt($chunk, $decryptedChunk, $key);
            $decrypted .= $decryptedChunk;
        }

        return $decrypted;
    }
}
