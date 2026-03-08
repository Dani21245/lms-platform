<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class InitiatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_id' => ['required', 'exists:courses,id'],
            'payment_method' => ['sometimes', 'in:telebirr'],
        ];
    }
}
