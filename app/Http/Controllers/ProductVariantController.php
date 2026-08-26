<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'attribute' => 'required|string|max:255',
            'value' => 'required|string|max:255',
            'price_adjustment' => 'nullable|numeric',
            'stock' => 'required|integer|min:0',
            'sku' => 'nullable|string|unique:product_variants,sku',
        ]);

        $validated['product_id'] = $product->id;

        $variant = ProductVariant::create($validated);

        return response()->json($variant, 201);
    }

    public function update(Request $request, ProductVariant $productVariant)
    {
        $this->authorize('update', $productVariant->product);

        $validated = $request->validate([
            'attribute' => 'sometimes|required|string|max:255',
            'value' => 'sometimes|required|string|max:255',
            'price_adjustment' => 'nullable|numeric',
            'stock' => 'sometimes|required|integer|min:0',
            'sku' => 'nullable|string|unique:product_variants,sku,' . $productVariant->id,
        ]);

        $productVariant->update($validated);

        return response()->json($productVariant);
    }

    public function destroy(Request $request, ProductVariant $productVariant)
    {
        $this->authorize('update', $productVariant->product);

        $productVariant->delete();

        return response()->json(['message' => 'Variante supprimée']);
    }
}