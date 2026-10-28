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
            'color' => 'nullable|string|max:50',
            'order' => 'nullable|integer',
        ]);

        // La couleur doit exister dans les variantes (même graphie que la variante)
        $color = null;
        if (!empty($validated['color'])) {
            $color = $product->variants()->where('color', $validated['color'])->value('color');

            if ($color === null) {
                return response()->json([
                    'message' => "Cette couleur n'existe pas pour ce produit. Crée d'abord la variante.",
                ], 422);
            }
        }

        $path = $request->file('image')->store('products', 'public');

        $image = ProductImage::create([
            'product_id' => $product->id,
            'color' => $color,
            'url' => '/storage/' . $path,
            'order' => $validated['order'] ?? 0,
        ]);

        return response()->json([
            'id' => $image->id,
            'url' => $image->public_url,
            'order' => $image->order,
            'color' => $image->color,
        ], 201);
    }

    public function update(Request $request, ProductImage $productImage)
{
    $this->authorize('update', $productImage->product);

    $validated = $request->validate([
        'color' => 'nullable|string|max:50',
        'order' => 'nullable|integer',
    ]);

    if ($request->has('color')) {
        $color = trim((string) ($validated['color'] ?? ''));
        $color = $color === '' ? null : $color;

        if ($color && !$productImage->product->variants()->where('color', $color)->exists()) {
            return response()->json(['message' => "Cette couleur n'existe pas pour ce produit."], 422);
        }

        $productImage->color = $color;
    }

    if (isset($validated['order'])) {
        $productImage->order = $validated['order'];
    }

    $productImage->save();

    return response()->json($productImage->fresh());
}

   public function destroy(Request $request, ProductImage $productImage)
    {
        $this->authorize('update', $productImage->product);

        Storage::disk('public')->delete($productImage->storage_path);
        $productImage->delete();

        return response()->json(['message' => 'Image supprimée']);
    }

}