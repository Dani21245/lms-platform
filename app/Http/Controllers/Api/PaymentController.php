<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Jobs\ProcessPaymentVerification;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\TransactionLog;
use App\Services\TelebirrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(
        private readonly TelebirrService $telebirrService,
    ) {}

    public function initiate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'course_id' => ['required', 'exists:courses,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $course = Course::where('status', 'published')->findOrFail($request->input('course_id'));

        // Check if already enrolled
        $existingEnrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->exists();

        if ($existingEnrollment) {
            return response()->json([
                'message' => 'You are already enrolled in this course.',
            ], 409);
        }

        // Free course - enroll directly
        if ($course->isFree()) {
            Enrollment::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            return response()->json([
                'message' => 'Enrolled successfully (free course)',
                'enrolled' => true,
            ]);
        }

        // Check for pending payment
        $existingPayment = Payment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', 'pending')
            ->first();

        if ($existingPayment) {
            return response()->json([
                'message' => 'You have a pending payment for this course.',
                'payment' => new PaymentResource($existingPayment),
            ], 409);
        }

        // Create payment record
        $payment = Payment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'transaction_ref' => 'LMS-'.strtoupper(Str::random(12)),
            'payment_method' => 'telebirr',
            'amount' => $course->price,
            'currency' => $course->currency,
            'status' => 'pending',
        ]);

        // Initiate Telebirr payment
        $result = $this->telebirrService->initiatePayment($payment);

        if ($result['success']) {
            return response()->json([
                'message' => 'Payment initiated successfully',
                'payment' => new PaymentResource($payment),
                'payment_url' => $result['payment_url'],
            ]);
        }

        $payment->markAsFailed();

        return response()->json([
            'message' => $result['message'] ?? 'Failed to initiate payment',
        ], 500);
    }

    public function webhook(Request $request): JsonResponse
    {
        Log::info('Telebirr webhook received', [
            'data' => $request->all(),
            'ip' => $request->ip(),
        ]);

        $result = $this->telebirrService->verifyWebhook($request->all());

        if (! $result['valid']) {
            TransactionLog::create([
                'event_type' => 'webhook_invalid',
                'status' => 'failed',
                'request_data' => $request->all(),
                'ip_address' => $request->ip(),
            ]);

            return response()->json(['message' => 'Invalid webhook'], 400);
        }

        TransactionLog::create([
            'transaction_ref' => $result['out_trade_no'],
            'event_type' => 'webhook_received',
            'status' => $result['transaction_status'],
            'request_data' => $request->all(),
            'response_data' => $result['raw'],
            'ip_address' => $request->ip(),
        ]);

        // Process payment verification in background
        ProcessPaymentVerification::dispatch(
            $result['out_trade_no'],
            $result['trade_no'] ?? '',
            $result['transaction_status'],
            $result['raw'],
        );

        return response()->json([
            'code' => 0,
            'msg' => 'success',
        ]);
    }

    public function status(Request $request, string $transactionRef): JsonResponse
    {
        $payment = Payment::where('transaction_ref', $transactionRef)
            ->where('user_id', $request->user()->id)
            ->with(['course'])
            ->firstOrFail();

        return response()->json([
            'payment' => new PaymentResource($payment),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $payments = Payment::where('user_id', $request->user()->id)
            ->with(['course'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'payments' => PaymentResource::collection($payments),
        ]);
    }
}
