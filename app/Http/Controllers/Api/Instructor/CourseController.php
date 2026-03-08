<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Instructor\StoreCourseRequest;
use App\Http\Requests\Instructor\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $courses = $request->user()
            ->courses()
            ->with(['category'])
            ->withCount(['lessons', 'enrollments'])
            ->when($request->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->get('search'), fn ($q, $search) => $q->where('title', 'ilike', "%{$search}%"))
            ->latest()
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

    public function store(StoreCourseRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['instructor_id'] = $request->user()->id;
        $validated['slug'] = Str::slug($validated['title']) . '-' . Str::random(6);

        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = $request->file('thumbnail')->store('thumbnails', 'public');
        }

        $course = Course::create($validated);

        return response()->json([
            'message' => 'Course created successfully.',
            'data' => new CourseResource($course->load(['category'])),
        ], 201);
    }

    public function show(Request $request, Course $course): JsonResponse
    {
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $course->load(['category', 'lessons', 'enrollments.user']);

        return response()->json([
            'data' => new CourseResource($course),
        ]);
    }

    public function update(UpdateCourseRequest $request, Course $course): JsonResponse
    {
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validated();

        if (isset($validated['title'])) {
            $validated['slug'] = Str::slug($validated['title']) . '-' . Str::random(6);
        }

        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = $request->file('thumbnail')->store('thumbnails', 'public');
        }

        if (isset($validated['status']) && $validated['status'] === 'published') {
            $validated['published_at'] = now();
        }

        $course->update($validated);

        return response()->json([
            'message' => 'Course updated successfully.',
            'data' => new CourseResource($course->fresh()->load(['category'])),
        ]);
    }

    public function destroy(Request $request, Course $course): JsonResponse
    {
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $course->delete();

        return response()->json([
            'message' => 'Course deleted successfully.',
        ]);
    }

    public function students(Request $request, Course $course): JsonResponse
    {
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $enrollments = $course->enrollments()
            ->with('user')
            ->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $enrollments->map(fn ($e) => [
                'id' => $e->id,
                'student' => [
                    'id' => $e->user->id,
                    'name' => $e->user->name,
                    'email' => $e->user->email,
                ],
                'status' => $e->status,
                'progress' => $e->progress,
                'enrolled_at' => $e->enrolled_at,
                'completed_at' => $e->completed_at,
            ]),
            'meta' => [
                'current_page' => $enrollments->currentPage(),
                'last_page' => $enrollments->lastPage(),
                'total' => $enrollments->total(),
            ],
        ]);
    }
}
