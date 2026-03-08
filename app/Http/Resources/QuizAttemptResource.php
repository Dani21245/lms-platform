<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'quiz_id' => $this->quiz_id,
            'answers' => $this->answers,
            'score' => $this->score,
            'total_questions' => $this->total_questions,
            'passed' => $this->passed,
            'user' => new UserResource($this->whenLoaded('user')),
            'quiz' => new QuizResource($this->whenLoaded('quiz')),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
