<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Instructor\StoreQuizRequest;
use App\Http\Resources\QuizResource;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function store(StoreQuizRequest $request, Course $course, Lesson $lesson): JsonResponse
    {
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($lesson->quiz) {
            return response()->json(['message' => 'This lesson already has a quiz.'], 422);
        }

        $validated = $request->validated();

        $quiz = Quiz::create([
            'lesson_id' => $lesson->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'time_limit_minutes' => $validated['time_limit_minutes'] ?? null,
            'pass_percentage' => $validated['pass_percentage'] ?? 60,
            'max_attempts' => $validated['max_attempts'] ?? 3,
            'shuffle_questions' => $validated['shuffle_questions'] ?? false,
            'show_correct_answers' => $validated['show_correct_answers'] ?? true,
        ]);

        if (!empty($validated['questions'])) {
            foreach ($validated['questions'] as $index => $questionData) {
                QuizQuestion::create([
                    'quiz_id' => $quiz->id,
                    'question' => $questionData['question'],
                    'type' => $questionData['type'],
                    'options' => $questionData['options'] ?? null,
                    'correct_answer' => $questionData['correct_answer'],
                    'explanation' => $questionData['explanation'] ?? null,
                    'points' => $questionData['points'] ?? 1,
                    'sort_order' => $index,
                ]);
            }
        }

        return response()->json([
            'message' => 'Quiz created successfully.',
            'data' => new QuizResource($quiz->load('questions')),
        ], 201);
    }

    public function show(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $quiz = $lesson->quiz;

        if (!$quiz) {
            return response()->json(['message' => 'Quiz not found.'], 404);
        }

        $quiz->load('questions');

        return response()->json([
            'data' => new QuizResource($quiz),
        ]);
    }

    public function update(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $quiz = $lesson->quiz;

        if (!$quiz) {
            return response()->json(['message' => 'Quiz not found.'], 404);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'pass_percentage' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'max_attempts' => ['sometimes', 'integer', 'min:1'],
            'shuffle_questions' => ['sometimes', 'boolean'],
            'show_correct_answers' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        $quiz->update($validated);

        return response()->json([
            'message' => 'Quiz updated successfully.',
            'data' => new QuizResource($quiz->fresh()->load('questions')),
        ]);
    }

    public function addQuestion(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $quiz = $lesson->quiz;

        if (!$quiz) {
            return response()->json(['message' => 'Quiz not found.'], 404);
        }

        $validated = $request->validate([
            'question' => ['required', 'string'],
            'type' => ['required', 'in:multiple_choice,true_false,short_answer'],
            'options' => ['nullable', 'array'],
            'correct_answer' => ['required', 'array'],
            'explanation' => ['nullable', 'string'],
            'points' => ['sometimes', 'integer', 'min:1'],
        ]);

        $validated['quiz_id'] = $quiz->id;
        $validated['sort_order'] = $quiz->questions()->max('sort_order') + 1;

        $question = QuizQuestion::create($validated);

        return response()->json([
            'message' => 'Question added successfully.',
            'data' => $question,
        ], 201);
    }

    public function updateQuestion(Request $request, Course $course, Lesson $lesson, QuizQuestion $question): JsonResponse
    {
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'question' => ['sometimes', 'string'],
            'type' => ['sometimes', 'in:multiple_choice,true_false,short_answer'],
            'options' => ['nullable', 'array'],
            'correct_answer' => ['sometimes', 'array'],
            'explanation' => ['nullable', 'string'],
            'points' => ['sometimes', 'integer', 'min:1'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $question->update($validated);

        return response()->json([
            'message' => 'Question updated successfully.',
            'data' => $question->fresh(),
        ]);
    }

    public function deleteQuestion(Request $request, Course $course, Lesson $lesson, QuizQuestion $question): JsonResponse
    {
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $question->delete();

        return response()->json([
            'message' => 'Question deleted successfully.',
        ]);
    }

    public function destroy(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $quiz = $lesson->quiz;

        if (!$quiz) {
            return response()->json(['message' => 'Quiz not found.'], 404);
        }

        $quiz->delete();

        return response()->json([
            'message' => 'Quiz deleted successfully.',
        ]);
    }
}
