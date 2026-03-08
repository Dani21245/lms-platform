<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizQuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isInstructorOrAdmin = $user && ($user->isAdmin() || $user->isInstructor());

        return [
            'id' => $this->id,
            'question' => $this->question,
            'type' => $this->type,
            'options' => $this->options,
            'correct_answer' => $this->when($isInstructorOrAdmin, $this->correct_answer),
            'explanation' => $this->when($isInstructorOrAdmin, $this->explanation),
            'points' => $this->points,
            'sort_order' => $this->sort_order,
        ];
    }
}
