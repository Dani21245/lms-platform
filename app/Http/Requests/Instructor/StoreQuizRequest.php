<?php

namespace App\Http\Requests\Instructor;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'pass_percentage' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'max_attempts' => ['sometimes', 'integer', 'min:1'],
            'shuffle_questions' => ['sometimes', 'boolean'],
            'show_correct_answers' => ['sometimes', 'boolean'],
            'questions' => ['sometimes', 'array'],
            'questions.*.question' => ['required_with:questions', 'string'],
            'questions.*.type' => ['required_with:questions', 'in:multiple_choice,true_false,short_answer'],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.correct_answer' => ['required_with:questions', 'array'],
            'questions.*.explanation' => ['nullable', 'string'],
            'questions.*.points' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
