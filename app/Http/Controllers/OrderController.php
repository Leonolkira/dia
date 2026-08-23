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
        ]);

        $cart = Cart::where('user_id', $request->user()->id)->with('items.product', 'items.variant')->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Votre panier est vide.'], 422);
        }

        // Vérifie que tous les produits appartiennent au même vendeur (règle qu'on a choisie)
        $vendorIds = $cart->items->pluck('product.user_id')->unique();
        if ($vendorIds->count() > 1) {
            return response()->json([
                'message' => 'Votre panier contient des produits de plusieurs vendeurs. Veuillez commander séparément pour chaque vendeur.',
            ], 422);
        }

        $order = DB::transaction(function () use ($request, $validated, $cart, $vendorIds) {
            $total = $cart->items->sum(function ($item) {
                $price = $item->product->price + ($item->variant->price_adjustment ?? 0);
                return $price * $item->quantity;
            });

            $order = Order::create([
                'user_id' => $request->user()->id,
                'vendor_id' => $vendorIds->first(),
                'status' => 'en_attente',
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'non_paye',
                'total' => $total,
                'shipping_address' => $validated['shipping_address'],
                'shipping_city' => $validated['shipping_city'],
                'shipping_phone' => $validated['shipping_phone'],
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($cart->items as $item) {
                $unitPrice = $item->product->price + ($item->variant->price_adjustment ?? 0);

                $order->items()->create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => $item->product->name,
                    'unit_price' => $unitPrice,
                    'quantity' => $item->quantity,
                ]);

                // Décrémente le stock
                if ($item->variant) {
                    $item->variant->decrement('stock', $item->quantity);
                } else {
                    $item->product->decrement('stock', $item->quantity);
                }
            }

            // Vide le panier après la commande
            $cart->items()->delete();

            return $order;
        });

        $order->load('items', 'vendor');

        $response = ['order' => $order];

        // Si commande via WhatsApp, on génère le lien
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
        // Seul le vendeur concerné ou un admin peut changer le statut
        if ($order->vendor_id !== $request->user()->id && !$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:en_attente,confirmee,expediee,livree,annulee',
        ]);

        $order->update($validated);

        return response()->json($order);
    }
}