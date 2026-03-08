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
            'passing_score' => $this->passing_score,
            'time_limit_minutes' => $this->time_limit_minutes,
            'is_published' => $this->is_published,
            'questions' => QuizQuestionResource::collection($this->whenLoaded('questions')),
            'questions_count' => $this->whenCounted('questions'),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
