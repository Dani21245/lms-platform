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
            'slug' => $this->slug,
            'description' => $this->description,
            'content' => $this->when($this->shouldShowContent($request), $this->content),
            'type' => $this->type,
            'video_url' => $this->when($this->shouldShowContent($request), $this->video_url),
            'duration_minutes' => $this->duration_minutes,
            'sort_order' => $this->sort_order,
            'is_free' => $this->is_free,
            'is_published' => $this->is_published,
            'has_quiz' => $this->quiz !== null,
            'created_at' => $this->created_at,
        ];
    }

    protected function shouldShowContent(Request $request): bool
    {
        $user = $request->user();

        if (!$user) {
            return $this->is_free;
        }

        if ($user->isAdmin() || $user->isInstructor()) {
            return true;
        }

        if ($this->is_free) {
            return true;
        }

        // Check if enrolled
        return $this->course->enrollments()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }
}
