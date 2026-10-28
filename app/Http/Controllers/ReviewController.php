<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Product $product)
    {
        return $product->reviews()
            ->with('user:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($review) => $this->format($review))
            ->values();
    }

    public function store(Request $request, Product $product)
    {
        $user = $request->user();

        if (!$user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Confirme ton adresse e-mail pour publier un avis.'], 403);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:100',
            'comment' => 'nullable|string|max:1000',
        ]);

        if (Review::where('product_id', $product->id)->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Tu as déjà laissé un avis pour ce produit.'], 422);
        }

        // Achat vérifié : une commande livrée qui contient ce produit
        $order = Order::where('user_id', $user->id)
            ->where('status', 'livree')
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->first();

        try {
            $review = Review::create([
                'product_id' => $product->id,
                'user_id' => $user->id,
                'order_id' => $order?->id,
                'rating' => $validated['rating'],
                'title' => $validated['title'] ?? null,
                'comment' => $validated['comment'] ?? null,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Double clic : la contrainte unique (product_id, user_id) a déjà bloqué le second avis
            return response()->json(['message' => 'Tu as déjà laissé un avis pour ce produit.'], 422);
        }

        return response()->json($this->format($review->load('user:id,name')), 201);
    }

    public function update(Request $request, Review $review)
    {
        if ($review->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'title' => 'nullable|string|max:100',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review->update($validated);

        return response()->json($this->format($review->load('user:id,name')));
    }

    public function destroy(Request $request, Review $review)
    {
        if ($review->user_id !== $request->user()->id && !$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $review->delete();

        return response()->json(['message' => 'Avis supprimé']);
    }

    private function format(Review $review): array
    {
        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'title' => $review->title,
            'comment' => $review->comment,
            'created_at' => $review->created_at,
            'verified_purchase' => $review->order_id !== null,
            'user' => ['name' => $review->author_name],
        ];
    }
}