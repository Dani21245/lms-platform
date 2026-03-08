<?php

namespace App\Services;

use App\Models\OtpCode;

class OtpService
{
    public function generate(string $phone): OtpCode
    {
        // Invalidate any existing OTPs for this phone
        OtpCode::where('phone', $phone)
            ->where('is_used', false)
            ->update(['is_used' => true]);

        $length = config('sms.otp_length', 6);
        $code = str_pad((string) random_int(0, (int) pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);

        return OtpCode::create([
            'phone' => $phone,
            'code' => $code,
            'expires_at' => now()->addMinutes(config('sms.otp_expiry_minutes', 5)),
        ]);
    }

    public function verify(string $phone, string $code): bool
    {
        $otp = OtpCode::where('phone', $phone)
            ->where('code', $code)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (! $otp) {
            return false;
        }

        $otp->update(['is_used' => true]);

        return true;
    }
}
