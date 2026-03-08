<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'code' => ['required', 'string', 'size:' . config('otp.length', 6)],
            'name' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
