<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuizResource;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InstructorQuizController extends Controller
{
    public function store(Request $request, int $courseId, int $lessonId): JsonResponse
    {
        $course = Course::where('instructor_id', $request->user()->id)->findOrFail($courseId);
        $lesson = $course->lessons()->findOrFail($lessonId);

        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'passing_score' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'is_published' => ['sometimes', 'boolean'],
            'questions' => ['sometimes', 'array'],
            'questions.*.question' => ['required_with:questions', 'string'],
            'questions.*.options' => ['required_with:questions', 'array', 'min:2'],
            'questions.*.correct_option' => ['required_with:questions', 'integer', 'min:0'],
            'questions.*.explanation' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $quiz = $lesson->quiz()->create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'passing_score' => $request->input('passing_score', 70),
            'time_limit_minutes' => $request->input('time_limit_minutes'),
            'is_published' => $request->input('is_published', false),
        ]);

        if ($request->has('questions')) {
            foreach ($request->input('questions') as $index => $questionData) {
                $quiz->questions()->create([
                    'question' => $questionData['question'],
                    'options' => $questionData['options'],
                    'correct_option' => $questionData['correct_option'],
                    'explanation' => $questionData['explanation'] ?? null,
                    'sort_order' => $index,
                ]);
            }
        }

        return response()->json([
            'message' => 'Quiz created successfully',
            'quiz' => new QuizResource($quiz->load('questions')),
        ], 201);
    }

    public function update(Request $request, int $courseId, int $lessonId, int $quizId): JsonResponse
    {
        $course = Course::where('instructor_id', $request->user()->id)->findOrFail($courseId);
        $lesson = $course->lessons()->findOrFail($lessonId);
        $quiz = $lesson->quiz()->findOrFail($quizId);

        $validator = Validator::make($request->all(), [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'passing_score' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $quiz->update($validator->validated());

        return response()->json([
            'message' => 'Quiz updated successfully',
            'quiz' => new QuizResource($quiz->fresh()->load('questions')),
        ]);
    }

    public function destroy(Request $request, int $courseId, int $lessonId, int $quizId): JsonResponse
    {
        $course = Course::where('instructor_id', $request->user()->id)->findOrFail($courseId);
        $lesson = $course->lessons()->findOrFail($lessonId);
        $quiz = $lesson->quiz()->findOrFail($quizId);

        $quiz->delete();

        return response()->json([
            'message' => 'Quiz deleted successfully',
        ]);
    }

    public function addQuestion(Request $request, int $courseId, int $lessonId, int $quizId): JsonResponse
    {
        $course = Course::where('instructor_id', $request->user()->id)->findOrFail($courseId);
        $lesson = $course->lessons()->findOrFail($lessonId);
        $quiz = $lesson->quiz()->findOrFail($quizId);

        $validator = Validator::make($request->all(), [
            'question' => ['required', 'string'],
            'options' => ['required', 'array', 'min:2'],
            'correct_option' => ['required', 'integer', 'min:0'],
            'explanation' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $maxOrder = $quiz->questions()->max('sort_order') ?? 0;

        $question = $quiz->questions()->create([
            'question' => $request->input('question'),
            'options' => $request->input('options'),
            'correct_option' => $request->input('correct_option'),
            'explanation' => $request->input('explanation'),
            'sort_order' => $maxOrder + 1,
        ]);

        return response()->json([
            'message' => 'Question added successfully',
            'question' => $question,
        ], 201);
    }

    public function updateQuestion(Request $request, int $courseId, int $lessonId, int $quizId, int $questionId): JsonResponse
    {
        $course = Course::where('instructor_id', $request->user()->id)->findOrFail($courseId);
        $lesson = $course->lessons()->findOrFail($lessonId);
        $quiz = $lesson->quiz()->findOrFail($quizId);
        $question = $quiz->questions()->findOrFail($questionId);

        $validator = Validator::make($request->all(), [
            'question' => ['sometimes', 'string'],
            'options' => ['sometimes', 'array', 'min:2'],
            'correct_option' => ['sometimes', 'integer', 'min:0'],
            'explanation' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $question->update($validator->validated());

        return response()->json([
            'message' => 'Question updated successfully',
            'question' => $question->fresh(),
        ]);
    }

    public function deleteQuestion(Request $request, int $courseId, int $lessonId, int $quizId, int $questionId): JsonResponse
    {
        $course = Course::where('instructor_id', $request->user()->id)->findOrFail($courseId);
        $lesson = $course->lessons()->findOrFail($lessonId);
        $quiz = $lesson->quiz()->findOrFail($quizId);
        $question = $quiz->questions()->findOrFail($questionId);

        $question->delete();

        return response()->json([
            'message' => 'Question deleted successfully',
        ]);
    }
}
