<?php

namespace App\Http\Requests\Instructor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'level' => ['sometimes', 'in:beginner,intermediate,advanced'],
            'language' => ['sometimes', 'string', 'max:10'],
            'duration_hours' => ['sometimes', 'integer', 'min:0'],
            'max_students' => ['nullable', 'integer', 'min:1'],
            'requirements' => ['nullable', 'array'],
            'requirements.*' => ['string'],
            'what_you_learn' => ['nullable', 'array'],
            'what_you_learn.*' => ['string'],
            'thumbnail' => ['nullable', 'image', 'max:5120'],
            'status' => ['sometimes', 'in:draft,pending,published,archived'],
        ];
    }
}
