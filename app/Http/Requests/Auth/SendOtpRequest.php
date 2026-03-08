<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Please provide a valid phone number.',
        ];
    }
}
