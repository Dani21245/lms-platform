<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuizAttemptResource;
use App\Http\Resources\QuizResource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\QuizAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StudentQuizController extends Controller
{
    public function show(Request $request, int $courseId, int $lessonId, int $quizId): JsonResponse
    {
        $userId = $request->user()->id;

        Enrollment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->firstOrFail();

        $course = Course::findOrFail($courseId);
        $lesson = $course->lessons()->findOrFail($lessonId);
        $quiz = $lesson->quiz()->with('questions')->findOrFail($quizId);

        return response()->json([
            'quiz' => new QuizResource($quiz),
        ]);
    }

    public function submit(Request $request, int $courseId, int $lessonId, int $quizId): JsonResponse
    {
        $userId = $request->user()->id;

        Enrollment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->firstOrFail();

        $course = Course::findOrFail($courseId);
        $lesson = $course->lessons()->findOrFail($lessonId);
        $quiz = $lesson->quiz()->with('questions')->findOrFail($quizId);

        $validator = Validator::make($request->all(), [
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.selected_option' => ['required', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $answers = collect($request->input('answers'));
        $questions = $quiz->questions;
        $totalQuestions = $questions->count();
        $correctAnswers = 0;

        $gradedAnswers = $answers->map(function ($answer) use ($questions, &$correctAnswers) {
            $question = $questions->firstWhere('id', $answer['question_id']);
            $isCorrect = $question && $question->correct_option === $answer['selected_option'];

            if ($isCorrect) {
                $correctAnswers++;
            }

            return [
                'question_id' => $answer['question_id'],
                'selected_option' => $answer['selected_option'],
                'is_correct' => $isCorrect,
                'correct_option' => $question?->correct_option,
                'explanation' => $question?->explanation,
            ];
        })->toArray();

        $score = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100) : 0;
        $passed = $score >= $quiz->passing_score;

        $attempt = QuizAttempt::create([
            'user_id' => $userId,
            'quiz_id' => $quiz->id,
            'answers' => $gradedAnswers,
            'score' => $score,
            'total_questions' => $totalQuestions,
            'passed' => $passed,
        ]);

        return response()->json([
            'message' => $passed ? 'Congratulations! You passed the quiz.' : 'You did not pass. Try again!',
            'attempt' => new QuizAttemptResource($attempt),
        ]);
    }

    public function attempts(Request $request, int $courseId, int $lessonId, int $quizId): JsonResponse
    {
        $userId = $request->user()->id;

        Enrollment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->firstOrFail();

        $attempts = QuizAttempt::where('user_id', $userId)
            ->where('quiz_id', $quizId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'attempts' => QuizAttemptResource::collection($attempts),
        ]);
    }
}
