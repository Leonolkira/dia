<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
            'order' => 'nullable|integer',
        ]);

        $path = $request->file('image')->store('products', 'public');

        $image = ProductImage::create([
            'product_id' => $product->id,
            'url' => asset('storage/' . $path),
            'order' => $validated['order'] ?? 0,
        ]);

        return response()->json($image, 201);
    }

    public function destroy(Request $request, ProductImage $productImage)
    {
        $this->authorize('update', $productImage->product);

        $path = str_replace(asset('storage/'), '', $productImage->url);
        Storage::disk('public')->delete($path);

        $productImage->delete();

        return response()->json(['message' => 'Image supprimée']);
    }
}