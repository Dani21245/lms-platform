<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Http\Resources\LessonResource;
use App\Models\Course;
use App\Models\LessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentCourseController extends Controller
{
    public function browse(Request $request): JsonResponse
    {
        $courses = Course::with(['instructor', 'category'])
            ->where('status', 'published')
            ->when($request->get('category_id'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->get('level'), fn ($q, $level) => $q->where('level', $level))
            ->when($request->get('search'), fn ($q, $search) => $q->where('title', 'ilike', "%{$search}%"))
            ->when($request->boolean('is_free'), fn ($q) => $q->where('price', 0))
            ->when($request->get('sort') === 'price_asc', fn ($q) => $q->orderBy('price'))
            ->when($request->get('sort') === 'price_desc', fn ($q) => $q->orderByDesc('price'))
            ->when($request->get('sort') === 'newest', fn ($q) => $q->latest('published_at'))
            ->when(!$request->get('sort'), fn ($q) => $q->latest('published_at'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => CourseResource::collection($courses),
            'meta' => [
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'per_page' => $courses->perPage(),
                'total' => $courses->total(),
            ],
        ]);
    }

    public function show(Course $course): JsonResponse
    {
        if (!$course->isPublished()) {
            return response()->json(['message' => 'Course not found.'], 404);
        }

        $course->load(['instructor', 'category', 'lessons']);

        return response()->json([
            'data' => new CourseResource($course),
        ]);
    }

    public function enrolled(Request $request): JsonResponse
    {
        $enrollments = $request->user()
            ->enrollments()
            ->with(['course.instructor', 'course.category'])
            ->when($request->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $enrollments->map(fn ($e) => [
                'enrollment_id' => $e->id,
                'status' => $e->status,
                'progress' => $e->progress,
                'enrolled_at' => $e->enrolled_at,
                'completed_at' => $e->completed_at,
                'course' => new CourseResource($e->course),
            ]),
            'meta' => [
                'current_page' => $enrollments->currentPage(),
                'last_page' => $enrollments->lastPage(),
                'total' => $enrollments->total(),
            ],
        ]);
    }

    public function lessons(Request $request, Course $course): JsonResponse
    {
        $user = $request->user();

        $isEnrolled = $user->enrollments()
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->exists();

        if (!$isEnrolled && !$course->isFree()) {
            return response()->json(['message' => 'You must be enrolled in this course.'], 403);
        }

        $lessons = $course->lessons()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->get();

        $progressMap = LessonProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', $lessons->pluck('id'))
            ->pluck('is_completed', 'lesson_id');

        return response()->json([
            'data' => $lessons->map(fn ($lesson) => [
                'lesson' => new LessonResource($lesson),
                'is_completed' => $progressMap[$lesson->id] ?? false,
            ]),
        ]);
    }

    public function markLessonComplete(Request $request, Course $course, int $lessonId): JsonResponse
    {
        $user = $request->user();

        $isEnrolled = $user->enrollments()
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->exists();

        if (!$isEnrolled) {
            return response()->json(['message' => 'You must be enrolled in this course.'], 403);
        }

        $lesson = $course->lessons()->where('id', $lessonId)->firstOrFail();

        LessonProgress::updateOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            ['is_completed' => true, 'completed_at' => now()]
        );

        // Update enrollment progress
        $totalLessons = $course->lessons()->where('is_published', true)->count();
        $completedLessons = LessonProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', $course->lessons()->pluck('id'))
            ->where('is_completed', true)
            ->count();

        $progress = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 2) : 0;

        $enrollment = $user->enrollments()
            ->where('course_id', $course->id)
            ->first();

        $enrollment->update([
            'progress' => $progress,
            'status' => $progress >= 100 ? 'completed' : 'active',
            'completed_at' => $progress >= 100 ? now() : null,
        ]);

        return response()->json([
            'message' => 'Lesson marked as complete.',
            'progress' => $progress,
        ]);
    }

    public function enrollFree(Request $request, Course $course): JsonResponse
    {
        if (!$course->isFree()) {
            return response()->json(['message' => 'This course is not free.'], 422);
        }

        if (!$course->isPublished()) {
            return response()->json(['message' => 'This course is not available.'], 422);
        }

        $enrollment = $request->user()->enrollments()->firstOrCreate(
            ['course_id' => $course->id],
            [
                'status' => 'active',
                'enrolled_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Enrolled successfully.',
            'data' => [
                'enrollment_id' => $enrollment->id,
                'course_id' => $course->id,
            ],
        ], 201);
    }
}
