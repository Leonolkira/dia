<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
{
    return Category::withCount('products')
        ->with(['children' => fn ($q) => $q->withCount('products')])
        ->whereNull('parent_id')
        ->get();
}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:categories,slug',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        $category = Category::create($validated);

        return response()->json($category, 201);
    }

    public function show(Category $category)
    {
        return $category->load(['children', 'products' => fn ($q) => $q->where('is_active', true)]);
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|unique:categories,slug,' . $category->id,
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        $category->update($validated);

        return response()->json($category);
    }

    public function destroy(Category $category)
{
    if ($category->products()->exists()) {
        return response()->json(['message' => 'Impossible de supprimer : cette catégorie contient encore des produits.'], 422);
    }
    if ($category->children()->exists()) {
        return response()->json(['message' => 'Impossible de supprimer : cette catégorie contient des sous-catégories.'], 422);
    }

    $category->delete();

    return response()->json(['message' => 'Catégorie supprimée']);
}
}