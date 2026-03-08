<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SubmitQuizRequest;
use App\Http\Resources\QuizResource;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentQuizController extends Controller
{
    public function show(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $user = $request->user();

        $isEnrolled = $user->enrollments()
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->exists();

        if (!$isEnrolled) {
            return response()->json(['message' => 'You must be enrolled in this course.'], 403);
        }

        $quiz = $lesson->quiz;

        if (!$quiz || !$quiz->is_published) {
            return response()->json(['message' => 'Quiz not available.'], 404);
        }

        $quiz->load('questions');

        $attemptCount = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->count();

        return response()->json([
            'data' => new QuizResource($quiz),
            'attempts_used' => $attemptCount,
            'attempts_remaining' => max(0, $quiz->max_attempts - $attemptCount),
        ]);
    }

    public function submit(SubmitQuizRequest $request, Course $course, Lesson $lesson): JsonResponse
    {
        $user = $request->user();

        $isEnrolled = $user->enrollments()
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->exists();

        if (!$isEnrolled) {
            return response()->json(['message' => 'You must be enrolled in this course.'], 403);
        }

        $quiz = $lesson->quiz;

        if (!$quiz || !$quiz->is_published) {
            return response()->json(['message' => 'Quiz not available.'], 404);
        }

        $attemptCount = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->count();

        if ($attemptCount >= $quiz->max_attempts) {
            return response()->json(['message' => 'Maximum attempts reached.'], 422);
        }

        $validated = $request->validated();
        $questions = $quiz->questions()->get()->keyBy('id');
        $score = 0;
        $totalPoints = 0;
        $results = [];

        foreach ($validated['answers'] as $answerData) {
            $question = $questions->get($answerData['question_id']);

            if (!$question) {
                continue;
            }

            $totalPoints += $question->points;
            $isCorrect = $this->checkAnswer($question, $answerData['answer']);

            if ($isCorrect) {
                $score += $question->points;
            }

            $results[] = [
                'question_id' => $question->id,
                'answer' => $answerData['answer'],
                'is_correct' => $isCorrect,
                'correct_answer' => $quiz->show_correct_answers ? $question->correct_answer : null,
                'explanation' => $quiz->show_correct_answers ? $question->explanation : null,
            ];
        }

        $percentage = $totalPoints > 0 ? round(($score / $totalPoints) * 100, 2) : 0;
        $passed = $percentage >= $quiz->pass_percentage;

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'answers' => $results,
            'score' => $score,
            'total_points' => $totalPoints,
            'percentage' => $percentage,
            'passed' => $passed,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => $passed ? 'Congratulations! You passed the quiz.' : 'You did not pass. Try again.',
            'data' => [
                'attempt_id' => $attempt->id,
                'score' => $score,
                'total_points' => $totalPoints,
                'percentage' => $percentage,
                'passed' => $passed,
                'results' => $results,
                'attempts_remaining' => max(0, $quiz->max_attempts - ($attemptCount + 1)),
            ],
        ]);
    }

    public function attempts(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $user = $request->user();
        $quiz = $lesson->quiz;

        if (!$quiz) {
            return response()->json(['message' => 'Quiz not found.'], 404);
        }

        $attempts = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'data' => $attempts->map(fn ($a) => [
                'id' => $a->id,
                'score' => $a->score,
                'total_points' => $a->total_points,
                'percentage' => $a->percentage,
                'passed' => $a->passed,
                'completed_at' => $a->completed_at,
            ]),
        ]);
    }

    protected function checkAnswer(object $question, mixed $answer): bool
    {
        $correctAnswer = $question->correct_answer;

        if ($question->type === 'true_false') {
            return strtolower((string) $answer) === strtolower((string) ($correctAnswer[0] ?? ''));
        }

        if ($question->type === 'short_answer') {
            return strtolower(trim((string) $answer)) === strtolower(trim((string) ($correctAnswer[0] ?? '')));
        }

        // multiple_choice
        if (is_array($answer)) {
            sort($answer);
            $correct = $correctAnswer;
            sort($correct);
            return $answer === $correct;
        }

        return in_array($answer, $correctAnswer);
    }
}
