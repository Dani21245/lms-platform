<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Instructor\StoreLessonRequest;
use App\Http\Resources\LessonResource;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LessonController extends Controller
{
    public function index(Request $request, Course $course): JsonResponse
    {
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $lessons = $course->lessons()
            ->with('quiz')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => LessonResource::collection($lessons),
        ]);
    }

    public function store(StoreLessonRequest $request, Course $course): JsonResponse
    {
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validated();
        $validated['course_id'] = $course->id;
        $validated['slug'] = Str::slug($validated['title']) . '-' . Str::random(6);

        if (!isset($validated['sort_order'])) {
            $validated['sort_order'] = $course->lessons()->max('sort_order') + 1;
        }

        if ($request->hasFile('video')) {
            $path = $request->file('video')->store('videos', 'public');
            $validated['video_path'] = $path;
            $validated['video_disk'] = 'public';
        }

        $lesson = Lesson::create($validated);

        return response()->json([
            'message' => 'Lesson created successfully.',
            'data' => new LessonResource($lesson),
        ], 201);
    }

    public function show(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $lesson->load('quiz.questions');

        return response()->json([
            'data' => new LessonResource($lesson),
        ]);
    }

    public function update(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'type' => ['sometimes', 'in:video,text,quiz,assignment'],
            'video_url' => ['nullable', 'url'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/mpeg,video/quicktime', 'max:512000'],
            'duration_minutes' => ['sometimes', 'integer', 'min:0'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_free' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['title'])) {
            $validated['slug'] = Str::slug($validated['title']) . '-' . Str::random(6);
        }

        if ($request->hasFile('video')) {
            $path = $request->file('video')->store('videos', 'public');
            $validated['video_path'] = $path;
            $validated['video_disk'] = 'public';
        }

        $lesson->update($validated);

        return response()->json([
            'message' => 'Lesson updated successfully.',
            'data' => new LessonResource($lesson->fresh()),
        ]);
    }

    public function destroy(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $lesson->delete();

        return response()->json([
            'message' => 'Lesson deleted successfully.',
        ]);
    }

    public function reorder(Request $request, Course $course): JsonResponse
    {
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'lessons' => ['required', 'array'],
            'lessons.*.id' => ['required', 'exists:lessons,id'],
            'lessons.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['lessons'] as $item) {
            Lesson::where('id', $item['id'])
                ->where('course_id', $course->id)
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json([
            'message' => 'Lessons reordered successfully.',
        ]);
    }
}
