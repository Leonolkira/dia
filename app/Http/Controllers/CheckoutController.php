<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CheckoutPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Notifier;

class CheckoutController extends Controller
{
    private const PAYMENT_LABELS = ['airtel_money' => 'Airtel Money', 'moov_money' => 'Moov Money'];

    public function quote(Request $request, CheckoutPricing $pricing)
    {
        $validated = $request->validate([
            'delivery_mode' => 'required|in:livraison,retrait',
            'payment_method' => 'nullable|in:airtel_money,moov_money',
            'coupon_code' => 'nullable|string|max:50',
        ]);

        $cart = Cart::where('user_id', $request->user()->id)->first();
        $subtotal = $cart ? $pricing->subtotal($cart) : 0.0;

        try {
            [$coupon, $discount] = $pricing->applyCoupon($validated['coupon_code'] ?? null, $subtotal);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($pricing->totals(
            $subtotal, $discount, $validated['delivery_mode'], $validated['payment_method'] ?? null
        ) + [
            'coupon_code' => $coupon?->code,
            'free_shipping_from' => (int) config('shop.free_shipping_from'),
        ]);
    }

    public function store(Request $request, CheckoutPricing $pricing)
    {
        
        $validated = $request->validate([
            'delivery_mode' => 'required|in:livraison,retrait',
            'payment_method' => 'required|in:airtel_money,moov_money',
            'shipping_name' => 'required|string|max:120',
            'shipping_phone' => 'required|string|max:30',
            'shipping_address' => 'required_if:delivery_mode,livraison|nullable|string|max:255',
            'shipping_city' => 'required_if:delivery_mode,livraison|nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'coupon_code' => 'nullable|string|max:50',
        ]);

        $cart = Cart::where('user_id', $request->user()->id)
            ->with('items.product', 'items.variant')
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Votre panier est vide.'], 422);
        }

        

        $isPickup = $validated['delivery_mode'] === 'retrait';

        try {
            $order = DB::transaction(function () use ($request, $validated, $cart, $pricing, $isPickup) {
                $lines = [];
                $subtotal = 0.0;

                foreach ($cart->items as $item) {
                    $product = Product::where('id', $item->product_id)->lockForUpdate()->first();

                    if (!$product || !$product->is_active) {
                        throw new \DomainException("Le produit \"{$item->product?->name}\" n'est plus disponible.");
                    }

                    $label = $product->name;
                    $variant = null;
                    $unitPrice = (float) $product->price;

                    if ($product->variants()->exists()) {
                        if (!$item->product_variant_id) {
                            throw new \DomainException("Veuillez choisir une option pour \"{$product->name}\".");
                        }

                        $variant = ProductVariant::where('id', $item->product_variant_id)
                            ->where('product_id', $product->id)
                            ->lockForUpdate()
                            ->first();

                        if (!$variant) {
                            throw new \DomainException("L'option choisie pour \"{$product->name}\" n'existe plus.");
                        }

                        $unitPrice += (float) $variant->price_adjustment;
                        $label = "{$product->name} ({$variant->label})";
                    }

                    // Produit sur commande : le stock n'est ni contrôlé ni décrémenté
                    if (!$product->on_order) {
                        $target = $variant ?? $product;

                        if ($target->stock < $item->quantity) {
                            throw new \DomainException("Stock insuffisant pour \"{$label}\".");
                        }

                        $target->decrement('stock', $item->quantity);
                    }

                    $lines[] = [
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'product_name' => $label,
                        'unit_price' => $unitPrice,
                        'quantity' => $item->quantity,
                    ];
                    $subtotal += $unitPrice * $item->quantity;
                }

                [$coupon, $discount] = $pricing->applyCoupon($validated['coupon_code'] ?? null, $subtotal, true);
                $totals = $pricing->totals($subtotal, $discount, $validated['delivery_mode'], $validated['payment_method']);

                $order = Order::create([
                    'user_id' => $request->user()->id,
                    'vendor_id' => null,
                    'coupon_id' => $coupon?->id,
                    'status' => 'en_attente',
                    'payment_method' => $validated['payment_method'],
                    'payment_status' => 'non_paye',
                    'delivery_mode' => $validated['delivery_mode'],
                    'shipping_name' => $validated['shipping_name'],
                    'total' => $totals['total'],
                    'discount' => $totals['discount'],
                    'shipping_fee' => $totals['shipping_fee'],
                    'deposit_amount' => $totals['deposit_amount'],
                    'shipping_address' => $isPickup ? config('shop.pickup_address') : $validated['shipping_address'],
                    'shipping_city' => $isPickup ? config('shop.pickup_city') : $validated['shipping_city'],
                    'shipping_phone' => $validated['shipping_phone'],
                    'notes' => $validated['notes'] ?? null,
                ]);

                foreach ($lines as $line) {
                    $order->items()->create($line);
                }

                if ($coupon) {
                    $coupon->increment('used_count');
                }

                $cart->items()->delete();

                return $order;
            });
        } catch (\DomainException $e) {
            // Seules les erreurs métier sont montrées au client ; les autres restent dans les logs
            return response()->json(['message' => $e->getMessage()], 422);
        }
        app(Notifier::class)->orderPlaced($order, $request->user());

        $order->load('items.product.images', 'vendor:id,name');

        return response()->json([
            'order' => (new OrderResource($order))->resolve(),
            'whatsapp_link' => $this->whatsappLink($order),
        ], 201);
    }

    private function whatsappLink(Order $order): ?string
    {
        $phone = preg_replace('/\D/', '', (string) config('shop.whatsapp'));
        if ($phone === '') {
            return null;
        }

        $lines = ["Bonjour DIA, je viens de passer la commande #{$order->id} :"];
        foreach ($order->items as $item) {
            $lines[] = "- {$item->product_name} x{$item->quantity}";
        }
        $lines[] = 'Total : ' . $this->fcfa($order->total);
        if ((float) $order->deposit_amount > 0) {
            $lines[] = 'Acompte à régler : ' . $this->fcfa($order->deposit_amount);
        }
        $lines[] = 'Paiement : ' . (self::PAYMENT_LABELS[$order->payment_method] ?? $order->payment_method);
        $lines[] = $order->delivery_mode === 'retrait'
            ? 'Retrait en magasin'
            : "Livraison : {$order->shipping_address}, {$order->shipping_city}";
        $lines[] = "Téléphone : {$order->shipping_phone}";

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode(implode("\n", $lines));
    }

    private function fcfa($value): string
    {
        return number_format((float) $value, 0, ',', ' ') . ' FCFA';
    }
}