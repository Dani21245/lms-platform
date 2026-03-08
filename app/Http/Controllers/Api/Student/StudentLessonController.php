<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StudentLessonController extends Controller
{
    public function show(Request $request, int $courseId, int $lessonId): JsonResponse
    {
        $userId = $request->user()->id;

        $enrollment = Enrollment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->firstOrFail();

        $course = Course::findOrFail($courseId);
        $lesson = $course->lessons()->with('quiz.questions')->findOrFail($lessonId);

        // Track progress
        LessonProgress::firstOrCreate([
            'user_id' => $userId,
            'lesson_id' => $lessonId,
        ]);

        return response()->json([
            'lesson' => new LessonResource($lesson),
        ]);
    }

    public function markComplete(Request $request, int $courseId, int $lessonId): JsonResponse
    {
        $userId = $request->user()->id;

        $enrollment = Enrollment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->firstOrFail();

        $course = Course::findOrFail($courseId);
        $lesson = $course->lessons()->findOrFail($lessonId);

        $progress = LessonProgress::updateOrCreate(
            ['user_id' => $userId, 'lesson_id' => $lessonId],
            ['is_completed' => true, 'completed_at' => now()]
        );

        // Recalculate course progress
        $totalLessons = $course->lessons()->count();
        $completedLessons = LessonProgress::where('user_id', $userId)
            ->where('is_completed', true)
            ->whereHas('lesson', function ($query) use ($courseId) {
                $query->where('course_id', $courseId);
            })
            ->count();

        $progressPercent = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 2) : 0;

        $updateData = ['progress' => $progressPercent];
        if ($progressPercent >= 100 && ! $enrollment->completed_at) {
            $updateData['completed_at'] = now();
        }
        $enrollment->update($updateData);

        return response()->json([
            'message' => 'Lesson marked as complete',
            'progress' => $progressPercent,
            'completed_at' => $progress->completed_at?->toISOString(),
        ]);
    }

    public function updateWatchTime(Request $request, int $courseId, int $lessonId): JsonResponse
    {
        $userId = $request->user()->id;

        Enrollment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->firstOrFail();

        // Validate lesson belongs to the course
        $course = Course::findOrFail($courseId);
        $lesson = $course->lessons()->findOrFail($lessonId);

        $validator = Validator::make($request->all(), [
            'watch_time_seconds' => ['required', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $progress = LessonProgress::updateOrCreate(
            ['user_id' => $userId, 'lesson_id' => $lesson->id],
            ['watch_time_seconds' => $request->input('watch_time_seconds')]
        );

        return response()->json([
            'message' => 'Watch time updated',
            'watch_time_seconds' => $progress->watch_time_seconds,
        ]);
    }
}
