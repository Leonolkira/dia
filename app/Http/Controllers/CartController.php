<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class CartController extends Controller
{
    // Récupère ou crée le panier de l'utilisateur connecté
    private function getOrCreateCart(Request $request): Cart
    {
        return Cart::firstOrCreate(['user_id' => $request->user()->id]);
    }

    public function index(Request $request)
    {
        $cart = $this->getOrCreateCart($request);
        $cart->load('items.product', 'items.variant');

        $total = $cart->items->sum(function ($item) {
            $price = $item->product->price + ($item->variant->price_adjustment ?? 0);
            return $price * $item->quantity;
        });

        return response()->json([
            'cart' => $cart,
            'total' => round($total, 2),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = $this->getOrCreateCart($request);

        // Vérifie si ce produit (+ variante) est déjà dans le panier
        $item = $cart->items()
            ->where('product_id', $validated['product_id'])
            ->where('product_variant_id', $validated['product_variant_id'] ?? null)
            ->first();

        if ($item) {
            // Si déjà présent, on augmente juste la quantité
            $item->increment('quantity', $validated['quantity']);
        } else {
            $item = $cart->items()->create($validated);
        }

        return response()->json($item->load('product', 'variant'), 201);
    }

    public function update(Request $request, $itemId)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = $this->getOrCreateCart($request);
        $item = $cart->items()->findOrFail($itemId);

        $item->update($validated);

        return response()->json($item->load('product', 'variant'));
    }

    public function destroy(Request $request, $itemId)
    {
        $cart = $this->getOrCreateCart($request);
        $item = $cart->items()->findOrFail($itemId);

        $item->delete();

        return response()->json(['message' => 'Article retiré du panier']);
    }

    public function clear(Request $request)
    {
        $cart = $this->getOrCreateCart($request);
        $cart->items()->delete();

        return response()->json(['message' => 'Panier vidé']);
    }
}