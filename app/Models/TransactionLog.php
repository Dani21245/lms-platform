<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionLog extends Model
{
    protected $fillable = [
        'payment_id',
        'transaction_ref',
        'event_type',
        'status',
        'request_data',
        'response_data',
        'ip_address',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
