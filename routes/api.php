<?php

use App\Http\Controllers\Api\Admin\AdminCategoryController;
use App\Http\Controllers\Api\Admin\AdminCourseController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminPaymentController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Auth\OtpAuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\Instructor\InstructorCourseController;
use App\Http\Controllers\Api\Instructor\InstructorDashboardController;
use App\Http\Controllers\Api\Instructor\InstructorLessonController;
use App\Http\Controllers\Api\Instructor\InstructorQuizController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\Student\StudentDashboardController;
use App\Http\Controllers\Api\Student\StudentLessonController;
use App\Http\Controllers\Api\Student\StudentQuizController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check
Route::get('/health', fn () => response()->json(['status' => 'ok', 'timestamp' => now()->toISOString()]));

// ──────────────────────────────────────────────
// Authentication (SMS OTP)
// ──────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/otp/request', [OtpAuthController::class, 'requestOtp'])
        ->middleware('throttle:5,1');
    Route::post('/otp/verify', [OtpAuthController::class, 'verifyOtp'])
        ->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [OtpAuthController::class, 'logout']);
        Route::get('/me', [OtpAuthController::class, 'me']);
    });
});

// ──────────────────────────────────────────────
// Public Routes
// ──────────────────────────────────────────────
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{slug}', [CourseController::class, 'show']);
Route::get('/courses/{courseId}/lessons', [CourseController::class, 'lessons']);

// ──────────────────────────────────────────────
// Payment Webhook (no auth required)
// ──────────────────────────────────────────────
Route::post('/payments/telebirr/webhook', [PaymentController::class, 'webhook']);

// ──────────────────────────────────────────────
// Authenticated Routes
// ──────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // ── Payments ──
    Route::prefix('payments')->group(function () {
        Route::post('/initiate', [PaymentController::class, 'initiate']);
        Route::get('/history', [PaymentController::class, 'history']);
        Route::get('/status/{transactionRef}', [PaymentController::class, 'status']);
    });

    // ──────────────────────────────────────────
    // Instructor Routes
    // ──────────────────────────────────────────
    Route::middleware('role:instructor,admin')->prefix('instructor')->group(function () {
        // Dashboard
        Route::get('/dashboard', [InstructorDashboardController::class, 'stats']);
        Route::get('/earnings', [InstructorDashboardController::class, 'earnings']);
        Route::get('/students', [InstructorDashboardController::class, 'students']);

        // Courses
        Route::apiResource('courses', InstructorCourseController::class);
        Route::post('/courses/{courseId}/thumbnail', [InstructorCourseController::class, 'uploadThumbnail']);

        // Lessons
        Route::get('/courses/{courseId}/lessons', [InstructorLessonController::class, 'index']);
        Route::post('/courses/{courseId}/lessons', [InstructorLessonController::class, 'store']);
        Route::get('/courses/{courseId}/lessons/{lessonId}', [InstructorLessonController::class, 'show']);
        Route::put('/courses/{courseId}/lessons/{lessonId}', [InstructorLessonController::class, 'update']);
        Route::delete('/courses/{courseId}/lessons/{lessonId}', [InstructorLessonController::class, 'destroy']);
        Route::post('/courses/{courseId}/lessons/reorder', [InstructorLessonController::class, 'reorder']);

        // Quizzes
        Route::post('/courses/{courseId}/lessons/{lessonId}/quiz', [InstructorQuizController::class, 'store']);
        Route::put('/courses/{courseId}/lessons/{lessonId}/quiz/{quizId}', [InstructorQuizController::class, 'update']);
        Route::delete('/courses/{courseId}/lessons/{lessonId}/quiz/{quizId}', [InstructorQuizController::class, 'destroy']);

        // Quiz Questions
        Route::post('/courses/{courseId}/lessons/{lessonId}/quiz/{quizId}/questions', [InstructorQuizController::class, 'addQuestion']);
        Route::put('/courses/{courseId}/lessons/{lessonId}/quiz/{quizId}/questions/{questionId}', [InstructorQuizController::class, 'updateQuestion']);
        Route::delete('/courses/{courseId}/lessons/{lessonId}/quiz/{quizId}/questions/{questionId}', [InstructorQuizController::class, 'deleteQuestion']);
    });

    // ──────────────────────────────────────────
    // Student Routes
    // ──────────────────────────────────────────
    Route::middleware('role:student,admin')->prefix('student')->group(function () {
        // Dashboard
        Route::get('/dashboard', [StudentDashboardController::class, 'stats']);
        Route::get('/courses', [StudentDashboardController::class, 'enrolledCourses']);
        Route::get('/courses/{courseId}/progress', [StudentDashboardController::class, 'courseProgress']);
        Route::get('/quiz-results', [StudentDashboardController::class, 'quizResults']);

        // Lesson interaction
        Route::get('/courses/{courseId}/lessons/{lessonId}', [StudentLessonController::class, 'show']);
        Route::post('/courses/{courseId}/lessons/{lessonId}/complete', [StudentLessonController::class, 'markComplete']);
        Route::post('/courses/{courseId}/lessons/{lessonId}/watch-time', [StudentLessonController::class, 'updateWatchTime']);

        // Quiz interaction
        Route::get('/courses/{courseId}/lessons/{lessonId}/quiz/{quizId}', [StudentQuizController::class, 'show']);
        Route::post('/courses/{courseId}/lessons/{lessonId}/quiz/{quizId}/submit', [StudentQuizController::class, 'submit']);
        Route::get('/courses/{courseId}/lessons/{lessonId}/quiz/{quizId}/attempts', [StudentQuizController::class, 'attempts']);
    });

    // ──────────────────────────────────────────
    // Admin Routes
    // ──────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // Dashboard
        Route::get('/dashboard', [AdminDashboardController::class, 'stats']);
        Route::get('/activity', [AdminDashboardController::class, 'recentActivity']);

        // User Management
        Route::apiResource('users', AdminUserController::class)->except(['store']);
        Route::post('/users/{id}/toggle-active', [AdminUserController::class, 'toggleActive']);

        // Course Management
        Route::apiResource('courses', AdminCourseController::class)->except(['store']);

        // Category Management
        Route::apiResource('categories', AdminCategoryController::class);

        // Payment Management
        Route::get('/payments', [AdminPaymentController::class, 'index']);
        Route::get('/payments/{id}', [AdminPaymentController::class, 'show']);
        Route::get('/transaction-logs', [AdminPaymentController::class, 'transactionLogs']);
    });
});
