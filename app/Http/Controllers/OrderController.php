<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        return Order::with('items')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);
    }

    public function show(Request $request, Order $order)
    {
        // Un client ne peut voir que ses propres commandes (sauf admin)
        if ($order->user_id !== $request->user()->id && !$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        return $order->load('items.product', 'items.variant', 'vendor');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:en_ligne,whatsapp',
            'shipping_address' => 'required|string|max:255',
            'shipping_city' => 'required|string|max:255',
            'shipping_phone' => 'required|string|max:20',
            'notes' => 'nullable|string',
            'coupon_code' => 'nullable|string',
        ]);

        $cart = Cart::where('user_id', $request->user()->id)->with('items.product', 'items.variant')->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Votre panier est vide.'], 422);
        }

        $vendorIds = $cart->items->pluck('product.user_id')->unique();
        if ($vendorIds->count() > 1) {
            return response()->json([
                'message' => 'Votre panier contient des produits de plusieurs vendeurs. Veuillez commander séparément pour chaque vendeur.',
            ], 422);
        }

        // Calcul du total brut (avant réduction)
        $subtotal = $cart->items->sum(function ($item) {
            $price = $item->product->price + ($item->variant->price_adjustment ?? 0);
            return $price * $item->quantity;
        });

        // Vérification du coupon si fourni
        $coupon = null;
        $discount = 0;

        if (!empty($validated['coupon_code'])) {
            $coupon = \App\Models\Coupon::where('code', $validated['coupon_code'])->first();

            if (!$coupon || !$coupon->isValid()) {
                return response()->json(['message' => 'Coupon invalide ou expiré.'], 422);
            }

            if ($coupon->min_purchase && $subtotal < $coupon->min_purchase) {
                return response()->json([
                    'message' => "Ce coupon nécessite un achat minimum de {$coupon->min_purchase} €.",
                ], 422);
            }

            $discount = $coupon->calculateDiscount($subtotal);
        }

        $total = round($subtotal - $discount, 2);

        try {
            $order = DB::transaction(function () use ($request, $validated, $cart, $vendorIds, $coupon, $discount, $total) {

                $order = Order::create([
                    'user_id' => $request->user()->id,
                    'vendor_id' => $vendorIds->first(),
                    'coupon_id' => $coupon?->id,
                    'status' => 'en_attente',
                    'payment_method' => $validated['payment_method'],
                    'payment_status' => 'non_paye',
                    'total' => $total,
                    'discount' => $discount,
                    'shipping_address' => $validated['shipping_address'],
                    'shipping_city' => $validated['shipping_city'],
                    'shipping_phone' => $validated['shipping_phone'],
                    'notes' => $validated['notes'] ?? null,
                ]);

                foreach ($cart->items as $item) {

                    if (!$item->product->is_active) {
                        throw new \Exception("Le produit \"{$item->product->name}\" n'est plus disponible.");
                    }
                    // Verrouille la ligne produit (ou variante) le temps de la transaction
                    if ($item->product_variant_id) {
                        $variant = \App\Models\ProductVariant::where('id', $item->product_variant_id)
                            ->lockForUpdate()
                            ->first();

                        if (!$variant || $variant->stock < $item->quantity) {
                            throw new \Exception("Stock insuffisant pour la variante \"{$variant?->value}\" du produit \"{$item->product->name}\".");
                        }

                        $unitPrice = $item->product->price + $variant->price_adjustment;
                        $variant->decrement('stock', $item->quantity);
                    } else {
                        $product = \App\Models\Product::where('id', $item->product_id)
                            ->lockForUpdate()
                            ->first();

                        if (!$product || $product->stock < $item->quantity) {
                            throw new \Exception("Stock insuffisant pour le produit \"{$item->product->name}\".");
                        }

                        $unitPrice = $product->price;
                        $product->decrement('stock', $item->quantity);
                    }

                    $order->items()->create([
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'product_name' => $item->product->name,
                        'unit_price' => $unitPrice,
                        'quantity' => $item->quantity,
                    ]);
                }

                if ($coupon) {
                    $coupon->increment('used_count');
                }

                $cart->items()->delete();

                return $order;
            });
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }


        $order->load('items', 'vendor', 'coupon');

        $response = ['order' => $order];

        if ($order->payment_method === 'whatsapp' && $order->vendor && $order->vendor->phone) {
            $response['whatsapp_link'] = $this->generateWhatsappLink($order);
        }

        return response()->json($response, 201);
    }

    private function generateWhatsappLink(Order $order): string
    {
        $lines = ["Nouvelle commande #{$order->id} :"];

        foreach ($order->items as $item) {
            $lines[] = "- {$item->product_name} x{$item->quantity} ({$item->unit_price} € l'unité)";
        }

        $lines[] = "Total : {$order->total} €";
        $lines[] = "Livraison : {$order->shipping_address}, {$order->shipping_city}";
        $lines[] = "Téléphone : {$order->shipping_phone}";

        $message = urlencode(implode("\n", $lines));
        $phone = preg_replace('/[^0-9]/', '', $order->vendor->phone); // nettoie le numéro

        return "https://wa.me/{$phone}?text={$message}";
    }

    public function updateStatus(Request $request, Order $order)
    {
        if ($order->vendor_id !== $request->user()->id && !$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:en_attente,confirmee,expediee,livree,annulee',
        ]);

        // Empêche de modifier une commande déjà livrée ou annulée (sauf admin)
        if (in_array($order->status, ['livree', 'annulee']) && !$request->user()->hasRole('admin')) {
            return response()->json([
                'message' => 'Cette commande est déjà finalisée et ne peut plus être modifiée.',
            ], 422);
        }

        $order->update($validated);

        return response()->json($order);
    }
}