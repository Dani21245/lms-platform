<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentResource;
use App\Http\Resources\QuizAttemptResource;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentDashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $totalEnrolled = Enrollment::where('user_id', $userId)->count();
        $completedCourses = Enrollment::where('user_id', $userId)
            ->whereNotNull('completed_at')
            ->count();
        $inProgressCourses = Enrollment::where('user_id', $userId)
            ->whereNull('completed_at')
            ->count();
        $averageProgress = Enrollment::where('user_id', $userId)->avg('progress') ?? 0;

        return response()->json([
            'stats' => [
                'total_enrolled' => $totalEnrolled,
                'completed_courses' => $completedCourses,
                'in_progress_courses' => $inProgressCourses,
                'average_progress' => round($averageProgress, 2),
            ],
        ]);
    }

    public function enrolledCourses(Request $request): JsonResponse
    {
        $enrollments = Enrollment::where('user_id', $request->user()->id)
            ->with(['course.instructor', 'course.category'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'enrollments' => EnrollmentResource::collection($enrollments),
        ]);
    }

    public function courseProgress(Request $request, int $courseId): JsonResponse
    {
        $userId = $request->user()->id;

        $enrollment = Enrollment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->with('course.lessons')
            ->firstOrFail();

        $lessonProgress = $request->user()->lessonProgress()
            ->whereHas('lesson', function ($query) use ($courseId) {
                $query->where('course_id', $courseId);
            })
            ->get()
            ->keyBy('lesson_id');

        $lessons = $enrollment->course->lessons->map(function ($lesson) use ($lessonProgress) {
            $progress = $lessonProgress->get($lesson->id);

            return [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'type' => $lesson->type->value,
                'duration_minutes' => $lesson->duration_minutes,
                'is_completed' => $progress?->is_completed ?? false,
                'watch_time_seconds' => $progress?->watch_time_seconds ?? 0,
            ];
        });

        return response()->json([
            'enrollment' => new EnrollmentResource($enrollment),
            'lessons_progress' => $lessons,
        ]);
    }

    public function quizResults(Request $request): JsonResponse
    {
        $attempts = $request->user()->quizAttempts()
            ->with(['quiz.lesson.course'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'quiz_attempts' => QuizAttemptResource::collection($attempts),
        ]);
    }
}
