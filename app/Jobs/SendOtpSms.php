<?php

namespace App\Jobs;

use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOtpSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        protected string $phone,
        protected string $code
    ) {}

    public function handle(SmsService $smsService): void
    {
        $appName = config('app.name', 'LMS');
        $message = "Your {$appName} verification code is: {$this->code}. Valid for " . config('otp.expiry_minutes', 5) . " minutes.";

        $smsService->send($this->phone, $message);
    }
}
