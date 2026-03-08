<?php

namespace App\Jobs;

use App\Services\TelebirrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaymentWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        protected array $payload,
        protected string $provider = 'telebirr'
    ) {}

    public function handle(TelebirrService $telebirrService): void
    {
        Log::info('Processing payment webhook', [
            'provider' => $this->provider,
            'payload_keys' => array_keys($this->payload),
        ]);

        if ($this->provider === 'telebirr') {
            $telebirrService->handleWebhook($this->payload);
        }
    }
}
