<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $products = $request->user()->wishlistProducts()
            ->where('products.is_active', true)
            ->with(['category:id,name,slug', 'images', 'variants'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->orderByPivot('created_at', 'desc')
            ->get();

        return ProductResource::collection($products);
    }

    public function ids(Request $request)
    {
        return response()->json($request->user()->wishlistProducts()->pluck('products.id'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['product_id' => 'required|exists:products,id']);

        $request->user()->wishlistProducts()->syncWithoutDetaching([$validated['product_id']]);

        return response()->json(['message' => 'Ajouté aux favoris'], 201);
    }

    public function destroy(Request $request, $productId)
    {
        $request->user()->wishlistProducts()->detach($productId);

        return response()->json(['message' => 'Retiré des favoris']);
    }
}