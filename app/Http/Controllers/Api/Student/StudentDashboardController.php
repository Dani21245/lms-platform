<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $enrollments = $user->enrollments()
            ->with('course.instructor')
            ->latest()
            ->get();

        $stats = [
            'total_enrolled' => $enrollments->count(),
            'in_progress' => $enrollments->where('status', 'active')->where('progress', '<', 100)->count(),
            'completed' => $enrollments->where('status', 'completed')->count(),
            'average_progress' => round($enrollments->where('status', 'active')->avg('progress') ?? 0, 1),
            'enrollments' => EnrollmentResource::collection($enrollments),
        ];

        return response()->json(['data' => $stats]);
    }
}
