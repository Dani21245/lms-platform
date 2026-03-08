<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\TransactionLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminPaymentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Payment::with(['user', 'course']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('transaction_ref', 'ilike', "%{$search}%")
                    ->orWhere('telebirr_transaction_id', 'ilike', "%{$search}%");
            });
        }

        return PaymentResource::collection(
            $query->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 15))
        );
    }

    public function show(int $id): JsonResponse
    {
        $payment = Payment::with(['user', 'course', 'transactionLogs'])->findOrFail($id);

        return response()->json([
            'payment' => new PaymentResource($payment),
            'transaction_logs' => $payment->transactionLogs()->orderBy('created_at', 'desc')->get(),
        ]);
    }

    public function transactionLogs(Request $request): JsonResponse
    {
        $query = TransactionLog::with('payment');

        if ($request->has('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }

        if ($request->has('transaction_ref')) {
            $query->where('transaction_ref', $request->input('transaction_ref'));
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 25));

        return response()->json([
            'transaction_logs' => $logs,
        ]);
    }
}
