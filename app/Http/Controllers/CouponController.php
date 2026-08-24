<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    // Réservé admin : gestion des coupons
    public function index()
    {
        return Coupon::latest()->paginate(15);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:coupons,code',
            'type' => 'required|in:pourcentage,montant_fixe',
            'value' => 'required|numeric|min:0',
            'min_purchase' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $coupon = Coupon::create($validated);

        return response()->json($coupon, 201);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $validated = $request->validate([
            'code' => 'sometimes|required|string|unique:coupons,code,' . $coupon->id,
            'type' => 'sometimes|required|in:pourcentage,montant_fixe',
            'value' => 'sometimes|required|numeric|min:0',
            'min_purchase' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
            'is_active' => 'boolean',
        ]);

        $coupon->update($validated);

        return response()->json($coupon);
    }

    public function destroy(Coupon $coupon)
    {
        $coupon->delete();

        return response()->json(['message' => 'Coupon supprimé']);
    }

    // Public : vérifier un coupon avant de l'appliquer (ex: dans le checkout)
    public function check(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'total' => 'required|numeric|min:0',
        ]);

        $coupon = Coupon::where('code', $validated['code'])->first();

        if (!$coupon || !$coupon->isValid()) {
            return response()->json(['message' => 'Coupon invalide ou expiré.'], 422);
        }

        if ($coupon->min_purchase && $validated['total'] < $coupon->min_purchase) {
            return response()->json([
                'message' => "Ce coupon nécessite un achat minimum de {$coupon->min_purchase} €.",
            ], 422);
        }

        $discount = $coupon->calculateDiscount($validated['total']);

        return response()->json([
            'valid' => true,
            'discount' => $discount,
            'new_total' => round($validated['total'] - $discount, 2),
        ]);
    }
}