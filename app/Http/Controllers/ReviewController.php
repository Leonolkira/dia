<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Product $product)
    {
        return $product->reviews()->with('user:id,name')->latest()->get();
    }

    public function store(Request $request, Product $product)
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        // Vérifie que l'utilisateur n'a pas déjà laissé un avis
        $existing = Review::where('product_id', $product->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Vous avez déjà laissé un avis pour ce produit.'], 422);
        }

        // Vérifie si l'utilisateur a réellement acheté ce produit (achat vérifié)
        $order = Order::where('user_id', $request->user()->id)
            ->whereHas('items', function ($query) use ($product) {
                $query->where('product_id', $product->id);
            })
            ->where('status', 'livree') // seulement si la commande a été livrée
            ->first();

        $review = Review::create([
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'order_id' => $order?->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return response()->json($review->load('user:id,name'), 201);
    }

    public function update(Request $request, Review $review)
    {
        if ($review->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review->update($validated);

        return response()->json($review);
    }

    public function destroy(Request $request, Review $review)
    {
        if ($review->user_id !== $request->user()->id && !$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $review->delete();

        return response()->json(['message' => 'Avis supprimé']);
    }
}