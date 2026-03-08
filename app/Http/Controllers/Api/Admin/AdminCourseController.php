<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;

class AdminCourseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Course::with(['instructor', 'category'])
            ->withCount(['lessons', 'enrollments']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('instructor_id')) {
            $query->where('instructor_id', $request->input('instructor_id'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        return CourseResource::collection(
            $query->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 15))
        );
    }

    public function show(int $id): JsonResponse
    {
        $course = Course::with(['instructor', 'category', 'lessons.quiz'])
            ->withCount(['lessons', 'enrollments'])
            ->findOrFail($id);

        return response()->json([
            'course' => new CourseResource($course),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $course = Course::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => ['sometimes', 'string', 'in:draft,published,archived'],
            'is_featured' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $course->update($validator->validated());

        return response()->json([
            'message' => 'Course updated successfully',
            'course' => new CourseResource($course->fresh()->load('instructor', 'category')),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $course = Course::findOrFail($id);
        $course->delete();

        return response()->json([
            'message' => 'Course deleted successfully',
        ]);
    }
}
