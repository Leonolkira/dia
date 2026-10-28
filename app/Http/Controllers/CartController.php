<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    private function getOrCreateCart(Request $request): Cart
    {
        return Cart::firstOrCreate(['user_id' => $request->user()->id]);
    }

    public function index(Request $request)
    {
        $cart = $this->getOrCreateCart($request);
        $cart->load(['items.product.images', 'items.variant']);

        $items = $cart->items->map(function ($item) {
            $product = $item->product;
            $variant = $item->variant;

            $unitPrice = (float) $product->price + (float) ($variant->price_adjustment ?? 0);
            $stock = $variant ? $variant->stock : $product->stock;

            // Photo de la couleur choisie, sinon photo générale, sinon la première
            $images = $product->images->sortBy('order');
            $image = ($variant?->color
                    ? $images->first(fn ($i) => $i->color === $variant->color)
                    : null)
                ?? $images->first(fn ($i) => $i->color === null)
                ?? $images->first();

            return [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice * $item->quantity,
                'on_order' => (bool) $product->on_order,
                'available_stock' => $product->on_order ? null : $stock,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'image' => $image?->public_url,
                ],
                'variant' => $variant ? [
                    'id' => $variant->id,
                    'color' => $variant->color,
                    'size' => $variant->size,
                    'label' => $variant->label,
                ] : null,
            ];
        })->values();

        $subtotal = round($items->sum('line_total'), 2);
        $totals = app(\App\Services\CheckoutPricing::class)->totals($subtotal, 0, 'livraison', null);

        return response()->json([
            'cart' => ['id' => $cart->id, 'items' => $items],
            'total' => $subtotal, // sous-total, hors livraison
            'shipping_fee' => $totals['shipping_fee'],
            'free_shipping_from' => (int) config('shop.free_shipping_from'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        $product = Product::with('variants')->findOrFail($validated['product_id']);

        if (!$product->is_active) {
            return response()->json(['message' => "Ce produit n'est plus disponible."], 422);
        }

        $variantId = $validated['product_variant_id'] ?? null;
        $variant = $variantId ? $product->variants->firstWhere('id', $variantId) : null;

        if ($variantId && !$variant) {
            return response()->json(['message' => "Cette option n'appartient pas à ce produit."], 422);
        }

        if ($product->variants->isNotEmpty() && !$variant) {
            return response()->json(['message' => 'Veuillez choisir une option (couleur, taille...).'], 422);
        }

        $cart = $this->getOrCreateCart($request);

        $item = $cart->items()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variantId)
            ->first();

        $newQuantity = ($item?->quantity ?? 0) + $validated['quantity'];

        if (!$product->on_order) {
            $available = $variant ? $variant->stock : $product->stock;

            if ($newQuantity > $available) {
                return response()->json([
                    'message' => "Stock insuffisant : il reste {$available} exemplaire(s).",
                ], 422);
            }
        }

        if ($item) {
            $item->update(['quantity' => $newQuantity]);
        } else {
            $item = $cart->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variantId,
                'quantity' => $newQuantity,
            ]);
        }

        return response()->json(['id' => $item->id, 'quantity' => $item->quantity], 201);
    }

    public function update(Request $request, $itemId)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        $cart = $this->getOrCreateCart($request);
        $item = $cart->items()->with('product', 'variant')->findOrFail($itemId);

        if (!$item->product->on_order) {
            $available = $item->variant ? $item->variant->stock : $item->product->stock;

            if ($validated['quantity'] > $available) {
                return response()->json([
                    'message' => "Stock insuffisant : il reste {$available} exemplaire(s).",
                ], 422);
            }
        }

        $item->update($validated);

        return response()->json(['id' => $item->id, 'quantity' => $item->quantity]);
    }

    public function destroy(Request $request, $itemId)
    {
        $cart = $this->getOrCreateCart($request);
        $cart->items()->findOrFail($itemId)->delete();

        return response()->json(['message' => 'Article retiré du panier']);
    }

    public function clear(Request $request)
    {
        $this->getOrCreateCart($request)->items()->delete();

        return response()->json(['message' => 'Panier vidé']);
    }
}