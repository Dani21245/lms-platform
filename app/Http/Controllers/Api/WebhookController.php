<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function telebirr(Request $request): JsonResponse
    {
        Log::info('Telebirr webhook received', [
            'ip' => $request->ip(),
            'payload_keys' => array_keys($request->all()),
        ]);

        ProcessPaymentWebhook::dispatch($request->all(), 'telebirr');

        return response()->json([
            'code' => 0,
            'msg' => 'success',
        ]);
    }
}
