<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminCategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Category::withCount('courses');

        if ($request->has('search')) {
            $query->where('name', 'ilike', '%'.$request->input('search').'%');
        }

        return CategoryResource::collection(
            $query->orderBy('name')->paginate($request->input('per_page', 15))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $category = Category::create([
            'name' => $request->input('name'),
            'slug' => Str::slug($request->input('name')),
            'description' => $request->input('description'),
            'is_active' => $request->input('is_active', true),
        ]);

        return response()->json([
            'message' => 'Category created successfully',
            'category' => new CategoryResource($category),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $category = Category::withCount('courses')->findOrFail($id);

        return response()->json([
            'category' => new CategoryResource($category),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255', 'unique:categories,name,'.$id],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->update($data);

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => new CategoryResource($category->fresh()),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }
}
