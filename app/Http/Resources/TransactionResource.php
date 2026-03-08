<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'payment_id' => $this->payment_id,
            'type' => $this->type,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'balance_after' => $this->balance_after,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
        ];
    }
}
