<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Course $course): JsonResponse
    {
        $reviews = $course->reviews()
            ->with('user')
            ->where('is_approved', true)
            ->latest()
            ->paginate(15);

        return response()->json([
            'data' => $reviews->map(fn ($r) => [
                'id' => $r->id,
                'rating' => $r->rating,
                'comment' => $r->comment,
                'user' => [
                    'id' => $r->user->id,
                    'name' => $r->user->name,
                    'avatar' => $r->user->avatar,
                ],
                'created_at' => $r->created_at,
            ]),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(),
                'average_rating' => $course->average_rating,
            ],
        ]);
    }

    public function store(Request $request, Course $course): JsonResponse
    {
        $user = $request->user();

        $isEnrolled = $user->enrollments()
            ->where('course_id', $course->id)
            ->exists();

        if (!$isEnrolled) {
            return response()->json(['message' => 'You must be enrolled in this course to leave a review.'], 403);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $review = CourseReview::updateOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            [
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'is_approved' => false,
            ]
        );

        return response()->json([
            'message' => 'Review submitted successfully. It will be visible after approval.',
            'data' => $review,
        ], 201);
    }
}
