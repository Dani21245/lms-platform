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
            'short_description' => $this->short_description,
            'thumbnail' => $this->thumbnail,
            'preview_video' => $this->preview_video,
            'price' => $this->price,
            'level' => $this->level,
            'language' => $this->language,
            'status' => $this->status,
            'duration_hours' => $this->duration_hours,
            'is_featured' => $this->is_featured,
            'max_students' => $this->max_students,
            'requirements' => $this->requirements,
            'what_you_learn' => $this->what_you_learn,
            'published_at' => $this->published_at,
            'average_rating' => $this->average_rating,
            'student_count' => $this->student_count,
            'instructor' => new UserResource($this->whenLoaded('instructor')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'lessons' => LessonResource::collection($this->whenLoaded('lessons')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
