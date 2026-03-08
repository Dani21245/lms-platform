<?php

namespace App\Jobs;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class VerifyPaymentStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $backoff = 60;

    public function __construct(
        protected int $paymentId
    ) {}

    public function handle(): void
    {
        $payment = Payment::find($this->paymentId);

        if (!$payment || !$payment->isPending()) {
            return;
        }

        // If payment is still pending after timeout, mark as expired
        if ($payment->created_at->addMinutes(30)->isPast()) {
            $payment->update(['status' => 'expired']);
            Log::info('Payment expired', ['payment_id' => $payment->id]);
            return;
        }

        // Re-dispatch for later check
        self::dispatch($this->paymentId)->delay(now()->addMinutes(5));
    }
}
