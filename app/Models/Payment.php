<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'transaction_ref',
        'payment_method',
        'amount',
        'currency',
        'status',
        'telebirr_transaction_id',
        'payment_data',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => PaymentStatus::class,
        'payment_data' => 'array',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function transactionLogs(): HasMany
    {
        return $this->hasMany(TransactionLog::class);
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === PaymentStatus::COMPLETED;
    }

    public function markAsCompleted(string $telebirrTransactionId): void
    {
        $this->update([
            'status' => PaymentStatus::COMPLETED,
            'telebirr_transaction_id' => $telebirrTransactionId,
            'paid_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => PaymentStatus::FAILED,
        ]);
    }
}
