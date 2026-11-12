<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $isAdmin = $request->user('sanctum')?->isAdmin() ?? false;
        $query = Category::query()->when(!$isAdmin, fn ($query) => $query->where('status', true));

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);
        $categories = $query
            ->withCount(['products' => fn ($products) => $products->where('status', true)])
            ->latest()
            ->paginate($perPage);

        return CategoryResource::collection($categories)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:categories,slug',
            'description' => 'nullable|string',
            'image' => 'nullable|string|max:255',
            'status' => 'boolean',
        ]);

        $category = Category::create($validated);

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(Category $category): JsonResponse
    {
        abort_unless($category->status, 404);

        return (new CategoryResource(
            $category->loadCount(['products' => fn ($products) => $products->where('status', true)])
        ))->response();
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:categories,slug,' . $category->id,
            'description' => 'nullable|string',
            'image' => 'nullable|string|max:255',
            'status' => 'boolean',
        ]);

        $category->update($validated);

        return (new CategoryResource($category))->response();
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        $this->authorizeAdmin($request);

        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Admin only');
    }
}