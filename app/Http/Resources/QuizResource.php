<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lesson_id' => $this->lesson_id,
            'title' => $this->title,
            'description' => $this->description,
            'time_limit_minutes' => $this->time_limit_minutes,
            'pass_percentage' => $this->pass_percentage,
            'max_attempts' => $this->max_attempts,
            'shuffle_questions' => $this->shuffle_questions,
            'show_correct_answers' => $this->show_correct_answers,
            'is_published' => $this->is_published,
            'total_points' => $this->total_points,
            'questions_count' => $this->questions->count(),
            'questions' => QuizQuestionResource::collection($this->whenLoaded('questions')),
            'created_at' => $this->created_at,
        ];
    }
}
