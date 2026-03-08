<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelebirrService
{
    protected string $appId;
    protected string $appKey;
    protected string $shortCode;
    protected string $publicKey;
    protected string $apiUrl;
    protected string $notifyUrl;
    protected string $returnUrl;
    protected string $timeoutUrl;

    public function __construct()
    {
        $this->appId = config('services.telebirr.app_id', '');
        $this->appKey = config('services.telebirr.app_key', '');
        $this->shortCode = config('services.telebirr.short_code', '');
        $this->publicKey = config('services.telebirr.public_key', '');
        $this->apiUrl = config('services.telebirr.api_url', 'https://app.ethiomobilemoney.et:2121');
        $this->notifyUrl = config('services.telebirr.notify_url', '');
        $this->returnUrl = config('services.telebirr.return_url', '');
        $this->timeoutUrl = config('services.telebirr.timeout_url', '');
    }

    public function initiatePayment(Payment $payment): array
    {
        $merchantOrderId = 'LMS-' . Str::upper(Str::random(12));
        $timestamp = now()->format('YmdHis');
        $nonce = Str::uuid()->toString();

        $payload = [
            'appId' => $this->appId,
            'notifyUrl' => $this->notifyUrl,
            'returnUrl' => $this->returnUrl,
            'timeoutUrl' => $this->timeoutUrl,
            'outTradeNo' => $merchantOrderId,
            'receiveName' => config('app.name', 'LMS Platform'),
            'shortCode' => $this->shortCode,
            'subject' => 'Course Payment - ' . $payment->course->title,
            'timeout' => '30',
            'timestamp' => $timestamp,
            'totalAmount' => number_format((float) $payment->amount, 2, '.', ''),
            'nonce' => $nonce,
        ];

        // Sort and sign
        ksort($payload);
        $signString = implode('&', array_map(
            fn ($key, $value) => "$key=$value",
            array_keys($payload),
            array_values($payload)
        ));

        $signedPayload = $this->signPayload($signString);

        $requestBody = [
            'appid' => $this->appId,
            'sign' => $signedPayload,
            'ussd' => $this->encryptPayload(json_encode($payload)),
        ];

        try {
            $response = Http::post("{$this->apiUrl}/ammq/payment/request", $requestBody);
            $result = $response->json();

            if (isset($result['code']) && $result['code'] === '200') {
                $payment->update([
                    'merchant_order_id' => $merchantOrderId,
                    'payment_data' => $result,
                ]);

                return [
                    'success' => true,
                    'checkout_url' => $result['data']['toPayUrl'] ?? null,
                    'merchant_order_id' => $merchantOrderId,
                ];
            }

            Log::error('Telebirr payment initiation failed', [
                'payment_id' => $payment->id,
                'response' => $result,
            ]);

            return [
                'success' => false,
                'message' => $result['msg'] ?? 'Payment initiation failed',
            ];
        } catch (\Exception $e) {
            Log::error('Telebirr payment exception', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Payment service unavailable',
            ];
        }
    }

    public function handleWebhook(array $data): bool
    {
        try {
            $decryptedData = $this->decryptPayload($data['result'] ?? '');
            $webhookPayload = json_decode($decryptedData, true);

            if (!$webhookPayload) {
                Log::error('Telebirr webhook: Failed to decrypt payload');
                return false;
            }

            $merchantOrderId = $webhookPayload['outTradeNo'] ?? null;
            $tradeStatus = $webhookPayload['tradeStatus'] ?? null;

            $payment = Payment::where('merchant_order_id', $merchantOrderId)->first();

            if (!$payment) {
                Log::error('Telebirr webhook: Payment not found', ['merchant_order_id' => $merchantOrderId]);
                return false;
            }

            $payment->update([
                'webhook_data' => $webhookPayload,
            ]);

            if ($tradeStatus === 'Completed' || $tradeStatus === 'SUCCESS') {
                $this->completePayment($payment);
                return true;
            }

            if (in_array($tradeStatus, ['Failed', 'FAIL', 'Cancelled'])) {
                $payment->update(['status' => 'failed']);
                $this->logTransaction($payment, 'payment', 'Payment failed via Telebirr');
                return true;
            }

            Log::warning('Telebirr webhook: Unknown trade status', [
                'status' => $tradeStatus,
                'merchant_order_id' => $merchantOrderId,
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Telebirr webhook exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function completePayment(Payment $payment): void
    {
        if ($payment->isCompleted()) {
            return;
        }

        $payment->update([
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        // Create enrollment
        $payment->user->enrollments()->firstOrCreate(
            ['course_id' => $payment->course_id],
            [
                'status' => 'active',
                'enrolled_at' => now(),
            ]
        );

        // Log transaction
        $this->logTransaction($payment, 'payment', 'Course payment completed via Telebirr');
    }

    protected function logTransaction(Payment $payment, string $type, string $description): void
    {
        Transaction::create([
            'user_id' => $payment->user_id,
            'payment_id' => $payment->id,
            'type' => $type,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'description' => $description,
            'metadata' => [
                'course_id' => $payment->course_id,
                'merchant_order_id' => $payment->merchant_order_id,
            ],
        ]);
    }

    protected function signPayload(string $data): string
    {
        $privateKey = openssl_pkey_get_private($this->appKey);
        if ($privateKey === false) {
            // Fallback: use HMAC-SHA256 if not RSA key
            return hash_hmac('sha256', $data, $this->appKey);
        }

        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    protected function encryptPayload(string $data): string
    {
        $publicKey = "-----BEGIN PUBLIC KEY-----\n" . $this->publicKey . "\n-----END PUBLIC KEY-----";
        $key = openssl_pkey_get_public($publicKey);

        if ($key === false) {
            return base64_encode($data);
        }

        $encrypted = '';
        $chunks = str_split($data, 117);

        foreach ($chunks as $chunk) {
            $partialEncrypted = '';
            openssl_public_encrypt($chunk, $partialEncrypted, $key, OPENSSL_PKCS1_PADDING);
            $encrypted .= $partialEncrypted;
        }

        return base64_encode($encrypted);
    }

    protected function decryptPayload(string $data): string
    {
        $decoded = base64_decode($data);

        $privateKey = openssl_pkey_get_private($this->appKey);
        if ($privateKey === false) {
            return $decoded;
        }

        $decrypted = '';
        $chunks = str_split($decoded, 256);

        foreach ($chunks as $chunk) {
            $partialDecrypted = '';
            openssl_private_decrypt($chunk, $partialDecrypted, $privateKey, OPENSSL_PKCS1_PADDING);
            $decrypted .= $partialDecrypted;
        }

        return $decrypted;
    }
}
