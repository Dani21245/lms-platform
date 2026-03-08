<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InstructorLessonController extends Controller
{
    public function index(Request $request, int $courseId): JsonResponse
    {
        $course = Course::where('instructor_id', $request->user()->id)->findOrFail($courseId);

        $lessons = $course->lessons()->with('quiz')->orderBy('sort_order')->get();

        return response()->json([
            'lessons' => LessonResource::collection($lessons),
        ]);
    }

    public function store(Request $request, int $courseId): JsonResponse
    {
        $course = Course::where('instructor_id', $request->user()->id)->findOrFail($courseId);

        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', 'string', 'in:video,text,quiz'],
            'video_url' => ['nullable', 'string', 'url'],
            'content' => ['nullable', 'string'],
            'duration_minutes' => ['sometimes', 'integer', 'min:0'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_free' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $maxOrder = $course->lessons()->max('sort_order') ?? 0;

        $lesson = $course->lessons()->create(array_merge(
            $validator->validated(),
            ['sort_order' => $request->input('sort_order', $maxOrder + 1)]
        ));

        $course->recalculateDuration();

        return response()->json([
            'message' => 'Lesson created successfully',
            'lesson' => new LessonResource($lesson),
        ], 201);
    }

    public function show(Request $request, int $courseId, int $lessonId): JsonResponse
    {
        $course = Course::where('instructor_id', $request->user()->id)->findOrFail($courseId);
        $lesson = $course->lessons()->with('quiz.questions')->findOrFail($lessonId);

        return response()->json([
            'lesson' => new LessonResource($lesson),
        ]);
    }

    public function update(Request $request, int $courseId, int $lessonId): JsonResponse
    {
        $course = Course::where('instructor_id', $request->user()->id)->findOrFail($courseId);
        $lesson = $course->lessons()->findOrFail($lessonId);

        $validator = Validator::make($request->all(), [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', 'string', 'in:video,text,quiz'],
            'video_url' => ['nullable', 'string', 'url'],
            'content' => ['nullable', 'string'],
            'duration_minutes' => ['sometimes', 'integer', 'min:0'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_free' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $lesson->update($validator->validated());
        $course->recalculateDuration();

        return response()->json([
            'message' => 'Lesson updated successfully',
            'lesson' => new LessonResource($lesson->fresh()),
        ]);
    }

    public function destroy(Request $request, int $courseId, int $lessonId): JsonResponse
    {
        $course = Course::where('instructor_id', $request->user()->id)->findOrFail($courseId);
        $lesson = $course->lessons()->findOrFail($lessonId);

        $lesson->delete();
        $course->recalculateDuration();

        return response()->json([
            'message' => 'Lesson deleted successfully',
        ]);
    }

    public function reorder(Request $request, int $courseId): JsonResponse
    {
        $course = Course::where('instructor_id', $request->user()->id)->findOrFail($courseId);

        $validator = Validator::make($request->all(), [
            'lessons' => ['required', 'array'],
            'lessons.*.id' => ['required', 'integer'],
            'lessons.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        foreach ($request->input('lessons') as $item) {
            $course->lessons()
                ->where('id', $item['id'])
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json([
            'message' => 'Lessons reordered successfully',
        ]);
    }
}
