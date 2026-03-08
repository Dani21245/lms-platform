<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type->value,
            'video_url' => $this->when($this->shouldShowVideoUrl($request), $this->video_url),
            'content' => $this->when($this->shouldShowContent($request), $this->content),
            'duration_minutes' => $this->duration_minutes,
            'sort_order' => $this->sort_order,
            'is_free' => $this->is_free,
            'is_published' => $this->is_published,
            'quiz' => new QuizResource($this->whenLoaded('quiz')),
            'created_at' => $this->created_at->toISOString(),
        ];
    }

    private function shouldShowVideoUrl(Request $request): bool
    {
        if ($this->is_free) {
            return true;
        }

        $user = $request->user();
        if (! $user) {
            return false;
        }

        // Instructor owns the course or admin
        if ($user->isAdmin() || $user->isInstructor()) {
            return true;
        }

        // Student is enrolled
        return $user->enrollments()
            ->where('course_id', $this->course_id)
            ->exists();
    }

    private function shouldShowContent(Request $request): bool
    {
        return $this->shouldShowVideoUrl($request);
    }
}
