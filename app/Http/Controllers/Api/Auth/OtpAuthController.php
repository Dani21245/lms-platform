<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Jobs\SendOtpSms;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OtpAuthController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
    ) {}

    public function requestOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^\+?[1-9]\d{6,14}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $request->input('phone');
        $otp = $this->otpService->generate($phone);

        SendOtpSms::dispatch($phone, $otp->code);

        return response()->json([
            'message' => 'OTP sent successfully',
            'expires_in_minutes' => config('sms.otp_expiry_minutes'),
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^\+?[1-9]\d{6,14}$/'],
            'code' => ['required', 'string', 'size:'.config('sms.otp_length', 6)],
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $request->input('phone');
        $code = $request->input('code');

        if (! $this->otpService->verify($phone, $code)) {
            return response()->json([
                'message' => 'Invalid or expired OTP',
            ], 401);
        }

        $user = User::where('phone', $phone)->first();
        $isNewUser = false;

        if (! $user) {
            $user = User::create([
                'phone' => $phone,
                'name' => $request->input('name', 'User'),
                'phone_verified_at' => now(),
            ]);
            $isNewUser = true;
        } else {
            if (! $user->phone_verified_at) {
                $user->update(['phone_verified_at' => now()]);
            }
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Your account has been deactivated. Please contact support.',
            ], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => $isNewUser ? 'Account created successfully' : 'Login successful',
            'user' => new UserResource($user),
            'token' => $token,
            'is_new_user' => $isNewUser,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }
}
