<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'requirements' => $this->requirements,
            'what_you_will_learn' => $this->what_you_will_learn,
            'thumbnail' => $this->thumbnail,
            'price' => $this->price,
            'currency' => $this->currency,
            'level' => $this->level,
            'language' => $this->language,
            'status' => $this->status->value,
            'is_featured' => $this->is_featured,
            'duration_minutes' => $this->duration_minutes,
            'instructor' => new UserResource($this->whenLoaded('instructor')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'lessons' => LessonResource::collection($this->whenLoaded('lessons')),
            'lessons_count' => $this->whenCounted('lessons'),
            'enrollments_count' => $this->whenCounted('enrollments'),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
