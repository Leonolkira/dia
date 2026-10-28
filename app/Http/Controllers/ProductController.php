<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Resources\ProductResource;

class ProductController extends Controller
{

public function index(Request $request)
{
    $validated = $request->validate([
        'category_id' => 'nullable|integer|exists:categories,id',
        'search' => 'nullable|string|max:100',
        'min_price' => 'nullable|numeric|min:0',
        'max_price' => 'nullable|numeric|min:0',
        'on_order' => 'nullable|boolean',
    ]);

    $query = Product::with(['category:id,name,slug', 'vendor:id,name', 'images', 'variants'])
        ->withAvg('reviews', 'rating')
        ->withCount('reviews')
        ->where('is_active', true);

    if (!empty($validated['category_id'])) {
        $ids = \App\Models\Category::where('parent_id', $validated['category_id'])
            ->pluck('id')
            ->push((int) $validated['category_id']);
        $query->whereIn('category_id', $ids);
    }
    if (!empty($validated['search'])) {
        $query->where('name', 'like', '%' . $validated['search'] . '%');
    }
    if ($request->boolean('on_order')) {
        $query->where('on_order', true);
    }
    if (!empty($validated['min_price'])) {
        $query->where('price', '>=', $validated['min_price']);
    }
    if (!empty($validated['max_price'])) {
        $query->where('price', '<=', $validated['max_price']);
    }

    return ProductResource::collection($query->paginate(15));
}

public function show(Request $request, Product $product)
{
    // Un produit désactivé n'est visible que par un admin ou son vendeur
    if (!$product->is_active) {
        $user = $request->user('sanctum');
        $canSee = $user && ($user->hasRole('admin') || $product->user_id === $user->id);
        abort_unless($canSee, 404);
    }

    $product->load([
        'category:id,name,slug',
        'vendor:id,name',
        'images',
        'variants',
        'reviews' => fn ($q) => $q->latest()->limit(20)->with('user:id,name'),
    ])->loadAvg('reviews', 'rating')->loadCount('reviews');

    return new ProductResource($product);
}

public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'slug' => 'required|string|unique:products,slug',
        'description' => 'nullable|string',
        'price' => 'required|numeric|min:0',
        'compare_price' => 'nullable|numeric|min:0',
        'stock' => 'required|integer|min:0',
        'on_order' => 'boolean',
        'lead_time_days' => 'nullable|integer|min:1|max:365',
        ...$this->detailsRules(),
        'sku' => 'nullable|string|unique:products,sku',
        'category_id' => 'required|exists:categories,id',
    ]);

    $validated['user_id'] = $request->user()->id;

    $product = Product::create($validated);

    return (new ProductResource($product->load('category', 'images', 'variants')))
        ->response()
        ->setStatusCode(201);
}

public function update(Request $request, Product $product)
{
    $this->authorize('update', $product);

    $validated = $request->validate([
        'name' => 'sometimes|required|string|max:255',
        'slug' => 'sometimes|required|string|unique:products,slug,' . $product->id,
        'description' => 'nullable|string',
        'price' => 'sometimes|required|numeric|min:0',
        'compare_price' => 'nullable|numeric|min:0',
        'stock' => 'sometimes|required|integer|min:0',
        'on_order' => 'boolean',
        'lead_time_days' => 'nullable|integer|min:1|max:365',
        ...$this->detailsRules(),
        'sku' => 'nullable|string|unique:products,sku,' . $product->id,
        'category_id' => 'sometimes|required|exists:categories,id',
        'is_active' => 'boolean',
    ]);

    // Avec des variantes, le stock du produit est ignoré
    if ($product->variants()->exists()) {
        unset($validated['stock']);
    }

    $product->update($validated);

    return new ProductResource($product->fresh(['category', 'images', 'variants']));
}

    public function destroy(Request $request, Product $product)
    {
        $this->authorize('delete', $product);

        $product->delete();

        return response()->json(['message' => 'Produit supprimé']);
    }

    // Empêche d'enregistrer n'importe quel JSON dans « details »
    private function detailsRules(): array
    {
        return [
            'details' => 'nullable|array',
            'details.summary' => 'nullable|string|max:1000',
            'details.specs' => 'nullable|array|max:8',
            'details.specs.*.label' => 'required|string|max:50',
            'details.specs.*.value' => 'required|string|max:50',
            'details.features' => 'nullable|array|max:12',
            'details.features.*' => 'required|string|max:80',
            'details.benefits' => 'nullable|array|max:6',
            'details.benefits.*.icon' => 'required|in:droplet,shirt,zap,heart',
            'details.benefits.*.title' => 'required|string|max:80',
            'details.benefits.*.description' => 'required|string|max:200',
            'details.idealFor' => 'nullable|array|max:12',
            'details.idealFor.*' => 'required|string|max:60',
            'details.care' => 'nullable|array|max:8',
            'details.care.*.label' => 'required|string|max:50',
            'details.care.*.value' => 'required|string|max:200',
        ];
    }
}