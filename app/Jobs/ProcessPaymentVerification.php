<?php

namespace App\Jobs;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\TransactionLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaymentVerification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        private readonly string $transactionRef,
        private readonly string $telebirrTransactionId,
        private readonly string $transactionStatus,
        private readonly array $rawData,
    ) {}

    public function handle(): void
    {
        $payment = Payment::where('transaction_ref', $this->transactionRef)->first();

        if (! $payment) {
            Log::error('Payment not found for verification', [
                'transaction_ref' => $this->transactionRef,
            ]);

            return;
        }

        TransactionLog::create([
            'payment_id' => $payment->id,
            'transaction_ref' => $this->transactionRef,
            'event_type' => 'webhook_processing',
            'status' => $this->transactionStatus,
            'response_data' => $this->rawData,
        ]);

        if ($this->transactionStatus === '2') {
            // Verify paid amount matches expected amount
            $paidAmount = (float) ($this->rawData['totalAmount'] ?? 0);
            $expectedAmount = (float) $payment->amount;

            if (abs($paidAmount - $expectedAmount) > 0.01) {
                Log::warning('Payment amount mismatch', [
                    'payment_id' => $payment->id,
                    'expected' => $expectedAmount,
                    'received' => $paidAmount,
                ]);

                $payment->markAsFailed();

                TransactionLog::create([
                    'payment_id' => $payment->id,
                    'transaction_ref' => $this->transactionRef,
                    'event_type' => 'amount_mismatch',
                    'status' => 'failed',
                    'response_data' => [
                        'expected' => $expectedAmount,
                        'received' => $paidAmount,
                    ],
                ]);

                return;
            }

            // Payment successful
            $payment->markAsCompleted($this->telebirrTransactionId);

            // Create enrollment
            Enrollment::firstOrCreate([
                'user_id' => $payment->user_id,
                'course_id' => $payment->course_id,
            ]);

            TransactionLog::create([
                'payment_id' => $payment->id,
                'transaction_ref' => $this->transactionRef,
                'event_type' => 'enrollment_created',
                'status' => 'success',
            ]);

            Log::info('Payment verified and enrollment created', [
                'payment_id' => $payment->id,
                'user_id' => $payment->user_id,
                'course_id' => $payment->course_id,
            ]);
        } else {
            $payment->markAsFailed();

            Log::warning('Payment verification failed', [
                'payment_id' => $payment->id,
                'status' => $this->transactionStatus,
            ]);
        }
    }
}
