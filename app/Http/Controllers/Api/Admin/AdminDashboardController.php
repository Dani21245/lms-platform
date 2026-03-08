<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        return response()->json([
            'stats' => [
                'total_users' => User::count(),
                'total_students' => User::where('role', 'student')->count(),
                'total_instructors' => User::where('role', 'instructor')->count(),
                'total_courses' => Course::count(),
                'published_courses' => Course::where('status', 'published')->count(),
                'total_enrollments' => Enrollment::count(),
                'total_revenue' => Payment::where('status', 'completed')->sum('amount'),
                'pending_payments' => Payment::where('status', 'pending')->count(),
            ],
        ]);
    }

    public function recentActivity(Request $request): JsonResponse
    {
        $recentEnrollments = Enrollment::with(['user', 'course'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $recentPayments = Payment::with(['user', 'course'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $recentUsers = User::orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'recent_enrollments' => $recentEnrollments,
            'recent_payments' => $recentPayments,
            'recent_users' => $recentUsers,
        ]);
    }
}
