<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentResource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstructorDashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $instructorId = $request->user()->id;

        $courseIds = Course::where('instructor_id', $instructorId)->pluck('id');

        $totalCourses = $courseIds->count();
        $publishedCourses = Course::where('instructor_id', $instructorId)
            ->where('status', 'published')
            ->count();
        $totalStudents = Enrollment::whereIn('course_id', $courseIds)->distinct('user_id')->count('user_id');
        $totalEarnings = Payment::whereIn('course_id', $courseIds)
            ->where('status', 'completed')
            ->sum('amount');
        $recentEnrollments = Enrollment::whereIn('course_id', $courseIds)
            ->with(['user', 'course'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'stats' => [
                'total_courses' => $totalCourses,
                'published_courses' => $publishedCourses,
                'total_students' => $totalStudents,
                'total_earnings' => $totalEarnings,
            ],
            'recent_enrollments' => EnrollmentResource::collection($recentEnrollments),
        ]);
    }

    public function earnings(Request $request): JsonResponse
    {
        $instructorId = $request->user()->id;
        $courseIds = Course::where('instructor_id', $instructorId)->pluck('id');

        $payments = Payment::whereIn('course_id', $courseIds)
            ->where('status', 'completed')
            ->with(['user', 'course'])
            ->orderBy('paid_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'total_earnings' => Payment::whereIn('course_id', $courseIds)
                ->where('status', 'completed')
                ->sum('amount'),
            'payments' => $payments,
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        $instructorId = $request->user()->id;
        $courseIds = Course::where('instructor_id', $instructorId)->pluck('id');

        $enrollments = Enrollment::whereIn('course_id', $courseIds)
            ->with(['user', 'course'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'enrollments' => EnrollmentResource::collection($enrollments),
            'total' => Enrollment::whereIn('course_id', $courseIds)->count(),
        ]);
    }
}
