<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstructorDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $courseIds = $user->courses()->pluck('id');

        $stats = [
            'total_courses' => $user->courses()->count(),
            'published_courses' => $user->courses()->where('status', 'published')->count(),
            'draft_courses' => $user->courses()->where('status', 'draft')->count(),
            'total_students' => Enrollment::whereIn('course_id', $courseIds)
                ->where('status', 'active')
                ->distinct('user_id')
                ->count('user_id'),
            'total_enrollments' => Enrollment::whereIn('course_id', $courseIds)->count(),
            'total_revenue' => Payment::whereIn('course_id', $courseIds)
                ->where('status', 'completed')
                ->sum('amount'),
            'revenue_this_month' => Payment::whereIn('course_id', $courseIds)
                ->where('status', 'completed')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount'),
            'recent_enrollments' => Enrollment::with(['user', 'course'])
                ->whereIn('course_id', $courseIds)
                ->latest()
                ->take(10)
                ->get()
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'student' => $e->user->name,
                    'course' => $e->course->title,
                    'enrolled_at' => $e->enrolled_at,
                    'progress' => $e->progress,
                ]),
        ];

        return response()->json(['data' => $stats]);
    }
}
