<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;

class ProductImageController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $validated = $request->validate([
            'url' => 'required|string|url',
            'order' => 'nullable|integer',
        ]);

        $validated['product_id'] = $product->id;

        $image = ProductImage::create($validated);

        return response()->json($image, 201);
    }

    public function destroy(ProductImage $productImage)
    {
        $productImage->delete();

        return response()->json(['message' => 'Image supprimée']);
    }
}