<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        return Wishlist::where('user_id', $request->user()->id)
            ->with('product.images', 'product.category')
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $wishlist = Wishlist::firstOrCreate([
            'user_id' => $request->user()->id,
            'product_id' => $validated['product_id'],
        ]);

        return response()->json($wishlist->load('product'), 201);
    }

    public function destroy(Request $request, $productId)
    {
        $deleted = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Produit non trouvé dans les favoris'], 404);
        }

        return response()->json(['message' => 'Retiré des favoris']);
    }
}