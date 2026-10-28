<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductVariantController extends Controller
{
    private function clean(?string $v): ?string
    {
        $v = trim((string) $v);
        return $v === '' ? null : $v;
    }

    private function duplicateExists(Product $product, ?string $color, ?string $size, ?int $ignoreId = null): bool
    {
        return $product->variants()
            ->where('color', $color)
            ->where('size', $size)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    public function store(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $request->merge([
            'color' => $this->clean($request->input('color')),
            'size' => $this->clean($request->input('size')),
        ]);

        $validated = $request->validate([
            'color' => 'nullable|string|max:50|required_without:size',
            'size' => 'nullable|string|max:50|required_without:color',
            'price_adjustment' => 'nullable|numeric',
            'stock' => 'required|integer|min:0',
            'sku' => 'nullable|string|unique:product_variants,sku',
        ]);

        if ($this->duplicateExists($product, $validated['color'] ?? null, $validated['size'] ?? null)) {
            return response()->json(['message' => 'Cette combinaison existe déjà pour ce produit.'], 422);
        }

        $validated['product_id'] = $product->id;

        return response()->json(ProductVariant::create($validated)->fresh(), 201);
    }

    public function update(Request $request, ProductVariant $productVariant)
    {
        $this->authorize('update', $productVariant->product);

        $validated = $request->validate([
            'price_adjustment' => 'nullable|numeric',
            'stock' => 'sometimes|required|integer|min:0',
            'sku' => 'nullable|string|unique:product_variants,sku,' . $productVariant->id,
        ]);

        $productVariant->update($validated);

        return response()->json($productVariant->fresh());
    }

    public function destroy(ProductVariant $productVariant)
    {
        $this->authorize('update', $productVariant->product);

        $product = $productVariant->product;
        $color = $productVariant->color;

        $productVariant->delete();

        // Plus aucune variante de cette couleur : on supprime ses images
        if ($color && !$product->variants()->where('color', $color)->exists()) {
            ProductImage::where('product_id', $product->id)->where('color', $color)->get()
                ->each(function ($image) {
                    Storage::disk('public')->delete(str_replace(asset('storage/'), '', $image->url));
                    $image->delete();
                });
        }

        return response()->json(['message' => 'Variante supprimée']);
    }
}