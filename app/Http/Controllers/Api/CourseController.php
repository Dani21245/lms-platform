<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Http\Resources\LessonResource;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Course::with(['instructor', 'category'])
            ->withCount(['lessons', 'enrollments'])
            ->where('status', 'published');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->has('level')) {
            $query->where('level', $request->input('level'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('is_featured')) {
            $query->where('is_featured', true);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $allowedSorts = ['created_at', 'price', 'title', 'duration_minutes'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        return CourseResource::collection(
            $query->paginate($request->input('per_page', 15))
        );
    }

    public function show(string $slug): JsonResponse
    {
        $course = Course::with(['instructor', 'category', 'lessons.quiz'])
            ->withCount(['lessons', 'enrollments'])
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return response()->json([
            'course' => new CourseResource($course),
        ]);
    }

    public function lessons(int $courseId): JsonResponse
    {
        $course = Course::where('status', 'published')->findOrFail($courseId);

        $lessons = $course->lessons()
            ->where('is_published', true)
            ->with('quiz')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'lessons' => LessonResource::collection($lessons),
        ]);
    }
}
