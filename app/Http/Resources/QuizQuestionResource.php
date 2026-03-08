<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizQuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quiz_id' => $this->quiz_id,
            'question' => $this->question,
            'options' => $this->options,
            'correct_option' => $this->when($this->shouldShowAnswer($request), $this->correct_option),
            'explanation' => $this->when($this->shouldShowAnswer($request), $this->explanation),
            'sort_order' => $this->sort_order,
        ];
    }

    private function shouldShowAnswer(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        return $user->isAdmin() || $user->isInstructor();
    }
}
