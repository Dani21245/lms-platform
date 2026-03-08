<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\TransactionResource;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function payments(Request $request): JsonResponse
    {
        $payments = Payment::with(['user', 'course'])
            ->when($request->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->get('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->get('from'), fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($request->get('to'), fn ($q, $to) => $q->where('created_at', '<=', $to))
            ->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => PaymentResource::collection($payments),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $transactions = Transaction::with(['user', 'payment'])
            ->when($request->get('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->get('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->get('from'), fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($request->get('to'), fn ($q, $to) => $q->where('created_at', '<=', $to))
            ->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => TransactionResource::collection($transactions),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }
}
