<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class InstructorCourseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $courses = Course::where('instructor_id', $request->user()->id)
            ->with(['category'])
            ->withCount(['lessons', 'enrollments'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return CourseResource::collection($courses);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'requirements' => ['nullable', 'string'],
            'what_you_will_learn' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'in:ETB,USD'],
            'level' => ['sometimes', 'string', 'in:beginner,intermediate,advanced'],
            'language' => ['sometimes', 'string', 'max:10'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $course = Course::create([
            'instructor_id' => $request->user()->id,
            'title' => $request->input('title'),
            'slug' => Str::slug($request->input('title')).'-'.Str::random(6),
            'description' => $request->input('description'),
            'requirements' => $request->input('requirements'),
            'what_you_will_learn' => $request->input('what_you_will_learn'),
            'category_id' => $request->input('category_id'),
            'price' => $request->input('price'),
            'currency' => $request->input('currency', 'ETB'),
            'level' => $request->input('level', 'beginner'),
            'language' => $request->input('language', 'en'),
            'status' => 'draft',
        ]);

        return response()->json([
            'message' => 'Course created successfully',
            'course' => new CourseResource($course->load('category')),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $course = Course::where('instructor_id', $request->user()->id)
            ->with(['category', 'lessons.quiz.questions'])
            ->withCount(['lessons', 'enrollments'])
            ->findOrFail($id);

        return response()->json([
            'course' => new CourseResource($course),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $course = Course::where('instructor_id', $request->user()->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'requirements' => ['nullable', 'string'],
            'what_you_will_learn' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'in:ETB,USD'],
            'level' => ['sometimes', 'string', 'in:beginner,intermediate,advanced'],
            'language' => ['sometimes', 'string', 'max:10'],
            'status' => ['sometimes', 'string', 'in:draft,published,archived'],
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
            'course' => new CourseResource($course->fresh()->load('category')),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $course = Course::where('instructor_id', $request->user()->id)->findOrFail($id);
        $course->delete();

        return response()->json([
            'message' => 'Course deleted successfully',
        ]);
    }

    public function uploadThumbnail(Request $request, int $id): JsonResponse
    {
        $course = Course::where('instructor_id', $request->user()->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'thumbnail' => ['required', 'image', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $path = $request->file('thumbnail')->store('thumbnails', 'public');
        $course->update(['thumbnail' => $path]);

        return response()->json([
            'message' => 'Thumbnail uploaded successfully',
            'thumbnail' => $path,
        ]);
    }
}
