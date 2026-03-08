<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $courses = Course::with(['instructor', 'category'])
            ->when($request->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->get('search'), fn ($q, $search) => $q->where('title', 'ilike', "%{$search}%"))
            ->when($request->get('instructor_id'), fn ($q, $id) => $q->where('instructor_id', $id))
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

    public function approve(Course $course): JsonResponse
    {
        $course->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        return response()->json([
            'message' => 'Course approved and published.',
            'data' => new CourseResource($course->fresh()),
        ]);
    }

    public function reject(Request $request, Course $course): JsonResponse
    {
        $course->update(['status' => 'draft']);

        return response()->json([
            'message' => 'Course rejected.',
            'data' => new CourseResource($course->fresh()),
        ]);
    }

    public function destroy(Course $course): JsonResponse
    {
        $course->delete();

        return response()->json([
            'message' => 'Course deleted successfully.',
        ]);
    }
}
