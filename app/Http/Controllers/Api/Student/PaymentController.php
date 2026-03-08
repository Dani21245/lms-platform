<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\InitiatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Jobs\VerifyPaymentStatus;
use App\Models\Course;
use App\Models\Payment;
use App\Services\TelebirrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(
        protected TelebirrService $telebirrService
    ) {}

    public function initiate(InitiatePaymentRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $course = Course::findOrFail($validated['course_id']);

        if (!$course->isPublished()) {
            return response()->json(['message' => 'Course is not available.'], 422);
        }

        if ($course->isFree()) {
            return response()->json(['message' => 'This course is free. Use the enroll endpoint instead.'], 422);
        }

        // Check if already enrolled
        $existingEnrollment = $user->enrollments()
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->first();

        if ($existingEnrollment) {
            return response()->json(['message' => 'You are already enrolled in this course.'], 422);
        }

        // Check for pending payment
        $pendingPayment = Payment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', 'pending')
            ->where('created_at', '>', now()->subMinutes(30))
            ->first();

        if ($pendingPayment) {
            return response()->json([
                'message' => 'You have a pending payment for this course.',
                'data' => new PaymentResource($pendingPayment),
            ], 422);
        }

        $payment = Payment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'payment_method' => $validated['payment_method'] ?? 'telebirr',
            'transaction_ref' => 'TXN-' . Str::upper(Str::random(16)),
            'amount' => $course->price,
            'currency' => 'ETB',
            'status' => 'pending',
        ]);

        $result = $this->telebirrService->initiatePayment($payment);

        if (!$result['success']) {
            $payment->update(['status' => 'failed']);

            return response()->json([
                'message' => $result['message'] ?? 'Payment initiation failed.',
            ], 500);
        }

        // Dispatch background job to verify payment status later
        VerifyPaymentStatus::dispatch($payment->id)->delay(now()->addMinutes(5));

        return response()->json([
            'message' => 'Payment initiated successfully.',
            'data' => [
                'payment' => new PaymentResource($payment->fresh()),
                'checkout_url' => $result['checkout_url'],
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $payments = $request->user()
            ->payments()
            ->with('course')
            ->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => PaymentResource::collection($payments),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    public function show(Request $request, Payment $payment): JsonResponse
    {
        if ($payment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $payment->load('course');

        return response()->json([
            'data' => new PaymentResource($payment),
        ]);
    }
}
