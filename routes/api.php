<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Admin\AdminCourseController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\TransactionController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Instructor\CourseController;
use App\Http\Controllers\Api\Instructor\InstructorDashboardController;
use App\Http\Controllers\Api\Instructor\LessonController;
use App\Http\Controllers\Api\Instructor\QuizController;
use App\Http\Controllers\Api\Student\PaymentController;
use App\Http\Controllers\Api\Student\ReviewController;
use App\Http\Controllers\Api\Student\StudentCourseController;
use App\Http\Controllers\Api\Student\StudentDashboardController;
use App\Http\Controllers\Api\Student\StudentQuizController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check
Route::get('/health', fn () => response()->json(['status' => 'ok', 'timestamp' => now()]));

// ─── Authentication ──────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/otp/send', [AuthController::class, 'sendOtp']);
    Route::post('/otp/verify', [AuthController::class, 'verifyOtp']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// ─── Public Routes ───────────────────────────────────────────────
Route::prefix('courses')->group(function () {
    Route::get('/', [StudentCourseController::class, 'browse']);
    Route::get('/{course}', [StudentCourseController::class, 'show']);
    Route::get('/{course}/reviews', [ReviewController::class, 'index']);
});

Route::get('/categories', [CategoryController::class, 'index']);

// ─── Webhook (no auth) ──────────────────────────────────────────
Route::post('/payments/telebirr/webhook', [WebhookController::class, 'telebirr']);

// ─── Authenticated Routes ────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // ─── Admin Routes ────────────────────────────────────────
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);

        // User management
        Route::get('/users', [UserManagementController::class, 'index']);
        Route::get('/users/{user}', [UserManagementController::class, 'show']);
        Route::put('/users/{user}', [UserManagementController::class, 'update']);
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy']);

        // Course management
        Route::get('/courses', [AdminCourseController::class, 'index']);
        Route::post('/courses/{course}/approve', [AdminCourseController::class, 'approve']);
        Route::post('/courses/{course}/reject', [AdminCourseController::class, 'reject']);
        Route::delete('/courses/{course}', [AdminCourseController::class, 'destroy']);

        // Categories
        Route::apiResource('categories', CategoryController::class)->except('index');

        // Transactions
        Route::get('/payments', [TransactionController::class, 'payments']);
        Route::get('/transactions', [TransactionController::class, 'transactions']);
    });

    // ─── Instructor Routes ───────────────────────────────────
    Route::prefix('instructor')->middleware('role:instructor,admin')->group(function () {
        Route::get('/dashboard', [InstructorDashboardController::class, 'index']);

        // Course CRUD
        Route::apiResource('courses', CourseController::class);
        Route::get('/courses/{course}/students', [CourseController::class, 'students']);

        // Lessons
        Route::get('/courses/{course}/lessons', [LessonController::class, 'index']);
        Route::post('/courses/{course}/lessons', [LessonController::class, 'store']);
        Route::get('/courses/{course}/lessons/{lesson}', [LessonController::class, 'show']);
        Route::put('/courses/{course}/lessons/{lesson}', [LessonController::class, 'update']);
        Route::delete('/courses/{course}/lessons/{lesson}', [LessonController::class, 'destroy']);
        Route::post('/courses/{course}/lessons/reorder', [LessonController::class, 'reorder']);

        // Quizzes
        Route::post('/courses/{course}/lessons/{lesson}/quiz', [QuizController::class, 'store']);
        Route::get('/courses/{course}/lessons/{lesson}/quiz', [QuizController::class, 'show']);
        Route::put('/courses/{course}/lessons/{lesson}/quiz', [QuizController::class, 'update']);
        Route::delete('/courses/{course}/lessons/{lesson}/quiz', [QuizController::class, 'destroy']);

        // Quiz questions
        Route::post('/courses/{course}/lessons/{lesson}/quiz/questions', [QuizController::class, 'addQuestion']);
        Route::put('/courses/{course}/lessons/{lesson}/quiz/questions/{question}', [QuizController::class, 'updateQuestion']);
        Route::delete('/courses/{course}/lessons/{lesson}/quiz/questions/{question}', [QuizController::class, 'deleteQuestion']);
    });

    // ─── Student Routes ──────────────────────────────────────
    Route::prefix('student')->middleware('role:student,admin')->group(function () {
        Route::get('/dashboard', [StudentDashboardController::class, 'index']);

        // Enrollments
        Route::get('/enrolled', [StudentCourseController::class, 'enrolled']);
        Route::post('/courses/{course}/enroll-free', [StudentCourseController::class, 'enrollFree']);
        Route::get('/courses/{course}/lessons', [StudentCourseController::class, 'lessons']);
        Route::post('/courses/{course}/lessons/{lesson}/complete', [StudentCourseController::class, 'markLessonComplete']);

        // Quizzes
        Route::get('/courses/{course}/lessons/{lesson}/quiz', [StudentQuizController::class, 'show']);
        Route::post('/courses/{course}/lessons/{lesson}/quiz/submit', [StudentQuizController::class, 'submit']);
        Route::get('/courses/{course}/lessons/{lesson}/quiz/attempts', [StudentQuizController::class, 'attempts']);

        // Payments
        Route::post('/payments/initiate', [PaymentController::class, 'initiate']);
        Route::get('/payments', [PaymentController::class, 'history']);
        Route::get('/payments/{payment}', [PaymentController::class, 'show']);

        // Reviews
        Route::post('/courses/{course}/reviews', [ReviewController::class, 'store']);
    });
});
