<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    use \Illuminate\Database\Eloquent\Prunable;

    protected $fillable = [
        'phone',
        'code',
        'expires_at',
        'is_used',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean',
    ];

    public function prunable()
    {
        return static::where('expires_at', '<', now()->subDay())
            ->orWhere('is_used', true);
    }

    public function isValid(): bool
    {
        return ! $this->is_used && $this->expires_at->isFuture();
    }
}
