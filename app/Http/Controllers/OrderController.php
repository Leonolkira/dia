<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Notifier;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['status' => 'nullable|in:en_attente,confirmee,expediee,livree,annulee']);

        $orders = Order::with('items.product.images')
            ->where('user_id', $request->user()->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(10);

        return OrderResource::collection($orders);
    }

    public function counts(Request $request)
    {
        $counts = Order::where('user_id', $request->user()->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($n) => (int) $n);

        return response()->json(['all' => (int) $counts->sum()] + $counts->all());
    }

    public function show(Request $request, Order $order)
    {
        $user = $request->user();

        if ($order->user_id !== $user->id && $order->vendor_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $order->load([
            'items.product.images',
            'vendor:id,name',
            'user' => fn ($q) => $q->withTrashed()->select('id', 'name'),
        ]);

        return new OrderResource($order);
    }

    public function updateStatus(Request $request, Order $order, Notifier $notifier)
{
    $user = $request->user();

    if ($order->vendor_id !== $user->id && !$user->hasRole('admin')) {
        return response()->json(['message' => 'Non autorisé'], 403);
    }

    $validated = $request->validate([
        'status' => 'sometimes|required|in:en_attente,confirmee,expediee,livree,annulee',
        'payment_status' => 'sometimes|required|in:non_paye,acompte_paye,paye,echoue',
    ]);

    if (in_array($order->status, ['livree', 'annulee']) && !$user->hasRole('admin')) {
        return response()->json([
            'message' => 'Cette commande est déjà finalisée et ne peut plus être modifiée.',
        ], 422);
    }

    try {
        $changes = DB::transaction(function () use ($order, $validated) {
            // Verrou : une double requête ne peut pas restituer deux fois le stock
            $order = Order::whereKey($order->id)->lockForUpdate()->first();

            $oldStatus = $order->status;
            $oldPayment = $order->payment_status;
            $newStatus = $validated['status'] ?? $oldStatus;

            // Annulation : le stock revient, une seule fois
            if ($newStatus === 'annulee' && $oldStatus !== 'annulee') {
                foreach ($order->items as $item) {
                    $product = Product::find($item->product_id);
                    if (!$product || $product->on_order) {
                        continue;
                    }

                    if ($item->product_variant_id) {
                        ProductVariant::where('id', $item->product_variant_id)->increment('stock', $item->quantity);
                    } else {
                        $product->increment('stock', $item->quantity);
                    }
                }

                if ($order->coupon_id) {
                    Coupon::where('id', $order->coupon_id)->where('used_count', '>', 0)->decrement('used_count');
                }
            }

            // Réactivation d'une commande annulée : on reprend le stock
            if ($oldStatus === 'annulee' && $newStatus !== 'annulee') {
                foreach ($order->items as $item) {
                    $product = Product::find($item->product_id);
                    if (!$product || $product->on_order) {
                        continue;
                    }

                    $model = $item->product_variant_id
                        ? ProductVariant::lockForUpdate()->find($item->product_variant_id)
                        : Product::lockForUpdate()->find($item->product_id);

                    if (!$model || $model->stock < $item->quantity) {
                        throw new \DomainException("Stock insuffisant pour réactiver la commande ({$item->product_name}).");
                    }
                    $model->decrement('stock', $item->quantity);
                }
            }

            $order->update($validated);

            return [
                'status' => $newStatus !== $oldStatus ? $newStatus : null,
                'payment' => isset($validated['payment_status']) && $validated['payment_status'] !== $oldPayment
                    ? $validated['payment_status']
                    : null,
            ];
        });
    } catch (\DomainException $e) {
        return response()->json(['message' => $e->getMessage()], 422);
    }

    $order = $order->fresh();
    $notifier->orderChanged($order, $changes['status'], $changes['payment']);

    return response()->json($order);
}
}