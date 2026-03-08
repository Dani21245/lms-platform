<?php

namespace App\Services;

use App\Models\Otp;
use Illuminate\Support\Str;

class OtpService
{
    public function generate(string $phone): Otp
    {
        // Invalidate previous OTPs for this phone
        Otp::where('phone', $phone)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        $length = (int) config('otp.length', 6);
        $code = str_pad((string) random_int(0, (int) pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);

        return Otp::create([
            'phone' => $phone,
            'code' => $code,
            'expires_at' => now()->addMinutes((int) config('otp.expiry_minutes', 5)),
        ]);
    }

    public function verify(string $phone, string $code): bool
    {
        $otp = Otp::where('phone', $phone)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otp) {
            return false;
        }

        $maxAttempts = (int) config('otp.max_attempts', 5);

        if ($otp->hasExceededAttempts($maxAttempts)) {
            return false;
        }

        $otp->increment('attempts');

        if ($otp->code !== $code) {
            return false;
        }

        $otp->update(['verified_at' => now()]);

        return true;
    }
}
