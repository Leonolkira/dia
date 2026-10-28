<?php

namespace App\Http\Controllers;

use App\Http\Resources\CustomRequestResource;
use App\Http\Resources\OrderResource;
use App\Models\CustomRequest;
use App\Models\Order;
use App\Services\CheckoutPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Notifier;

class CustomRequestController extends Controller
{
    private const PAYMENT_LABELS = ['airtel_money' => 'Airtel Money', 'moov_money' => 'Moov Money'];

    public function index(Request $request)
    {
        $requests = CustomRequest::with('product:id,name')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return CustomRequestResource::collection($requests);
    }

    public function show(Request $request, CustomRequest $customRequest)
    {
        abort_unless($customRequest->user_id === $request->user()->id, 404);

        return new CustomRequestResource($customRequest->load('product:id,name'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:120',
            'description' => 'required|string|min:10|max:2000',
            'size_info' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
            'quantity' => 'required|integer|min:1|max:50',
            'desired_date' => 'nullable|date|after:today',
            'product_id' => 'nullable|integer|exists:products,id',
            'images' => 'nullable|array|max:3',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $userId = $request->user()->id;

        if (CustomRequest::where('user_id', $userId)->where('status', 'en_attente')->count() >= 5) {
            return response()->json([
                'message' => "Vous avez déjà 5 demandes en attente de devis. Patientez avant d'en envoyer une nouvelle.",
            ], 422);
        }

        $paths = [];
        foreach ($request->file('images', []) as $file) {
            $paths[] = $file->store('custom-requests', 'public');
        }

        $customRequest = CustomRequest::create([
            'user_id' => $userId,
            'product_id' => $validated['product_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'size_info' => $validated['size_info'] ?? null,
            'color' => $validated['color'] ?? null,
            'quantity' => $validated['quantity'],
            'desired_date' => $validated['desired_date'] ?? null,
            'images' => $paths,
            'status' => 'en_attente',
        ]);
        app(Notifier::class)->requestCreated($customRequest, $request->user());

        return (new CustomRequestResource($customRequest->load('product:id,name')))
            ->response()
            ->setStatusCode(201);
    }

    /** Aperçu du total (livraison, acompte) avant d'accepter le devis */
    public function preview(Request $request, CustomRequest $customRequest, CheckoutPricing $pricing)
    {
        abort_unless($customRequest->user_id === $request->user()->id, 404);

        $validated = $request->validate([
            'delivery_mode' => 'required|in:livraison,retrait',
            'payment_method' => 'nullable|in:airtel_money,moov_money',
        ]);

        if ($customRequest->status !== 'devis_envoye') {
            return response()->json(['message' => "Ce devis n'est plus disponible."], 422);
        }

        if ($customRequest->is_expired) {
            return response()->json(['message' => 'Ce devis a expiré.'], 422);
        }

        return response()->json($pricing->totals(
            (float) $customRequest->quoted_price,
            0,
            $validated['delivery_mode'],
            $validated['payment_method'] ?? null
        ) + ['free_shipping_from' => (int) config('shop.free_shipping_from')]);
    }

    /** Le client accepte le devis : une commande est créée */
    public function accept(Request $request, CustomRequest $customRequest, CheckoutPricing $pricing)
    {
        abort_unless($customRequest->user_id === $request->user()->id, 404);

        $validated = $request->validate([
            'expected_price' => 'required|numeric',
            'delivery_mode' => 'required|in:livraison,retrait',
            'payment_method' => 'required|in:airtel_money,moov_money',
            'shipping_name' => 'required|string|max:120',
            'shipping_phone' => 'required|string|max:30',
            'shipping_address' => 'required_if:delivery_mode,livraison|nullable|string|max:255',
            'shipping_city' => 'required_if:delivery_mode,livraison|nullable|string|max:100',
        ]);

        $isPickup = $validated['delivery_mode'] === 'retrait';

        try {
            $order = DB::transaction(function () use ($request, $customRequest, $validated, $pricing, $isPickup) {
                $locked = CustomRequest::whereKey($customRequest->id)->lockForUpdate()->first();

                if ($locked->status !== 'devis_envoye' || $locked->quoted_price === null) {
                    throw new \DomainException("Ce devis n'est plus disponible.");
                }
                if ($locked->is_expired) {
                    throw new \DomainException('Ce devis a expiré. Écrivez-nous pour obtenir un nouveau devis.');
                }

                $price = (float) $locked->quoted_price;

                // Le client doit accepter le prix qu'il a vu
                if (abs($price - (float) $validated['expected_price']) > 0.001) {
                    throw new \DomainException('Le devis a été modifié. Rechargez la page pour voir le nouveau prix.');
                }

                $totals = $pricing->totals($price, 0, $validated['delivery_mode'], $validated['payment_method']);

                $order = Order::create([
                    'user_id' => $request->user()->id,
                    'vendor_id' => null,
                    'coupon_id' => null,
                    'status' => 'en_attente',
                    'payment_method' => $validated['payment_method'],
                    'payment_status' => 'non_paye',
                    'delivery_mode' => $validated['delivery_mode'],
                    'shipping_name' => $validated['shipping_name'],
                    'total' => $totals['total'],
                    'discount' => 0,
                    'shipping_fee' => $totals['shipping_fee'],
                    'deposit_amount' => $totals['deposit_amount'],
                    'shipping_address' => $isPickup ? config('shop.pickup_address') : $validated['shipping_address'],
                    'shipping_city' => $isPickup ? config('shop.pickup_city') : $validated['shipping_city'],
                    'shipping_phone' => $validated['shipping_phone'],
                    'notes' => "Demande sur mesure #{$locked->id}",
                ]);

                $order->items()->create([
                    'product_id' => null, // pas de produit du catalogue : aucun stock à gérer
                    'product_variant_id' => null,
                    'product_name' => 'Sur mesure : ' . $locked->title . ($locked->quantity > 1 ? " (x{$locked->quantity})" : ''),
                    'unit_price' => $price,
                    'quantity' => 1,
                ]);

                $locked->update(['status' => 'acceptee', 'order_id' => $order->id]);

                return $order;
            });
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        app(Notifier::class)->orderPlaced($order, $request->user(), $customRequest);

        $order->load('items');

        return response()->json([
            'order' => (new OrderResource($order))->resolve(),
            'whatsapp_link' => $this->whatsappLink($order, $customRequest),
        ], 201);
    }

    public function cancel(Request $request, CustomRequest $customRequest)
    {
        abort_unless($customRequest->user_id === $request->user()->id, 404);

        // Mise à jour conditionnelle : impossible d'annuler une demande acceptée entre-temps
        $updated = CustomRequest::whereKey($customRequest->id)
            ->whereIn('status', ['en_attente', 'devis_envoye'])
            ->update(['status' => 'annulee']);

        if ($updated === 0) {
            return response()->json(['message' => 'Cette demande ne peut plus être annulée.'], 422);
        }

        return new CustomRequestResource($customRequest->refresh()->load('product:id,name'));
    }

    private function whatsappLink(Order $order, CustomRequest $customRequest): ?string
    {
        $phone = preg_replace('/\D/', '', (string) config('shop.whatsapp'));
        if ($phone === '') {
            return null;
        }

        $lines = [
            "Bonjour DIA, j'ai accepté le devis de ma demande sur mesure #{$customRequest->id} (« {$customRequest->title} »).",
            "Commande #{$order->id}",
            'Total : ' . number_format((float) $order->total, 0, ',', ' ') . ' FCFA',
        ];
        if ((float) $order->deposit_amount > 0) {
            $lines[] = 'Acompte à régler : ' . number_format((float) $order->deposit_amount, 0, ',', ' ') . ' FCFA';
        }
        $lines[] = 'Paiement : ' . (self::PAYMENT_LABELS[$order->payment_method] ?? $order->payment_method);
        $lines[] = $order->delivery_mode === 'retrait'
            ? 'Retrait en magasin'
            : "Livraison : {$order->shipping_address}, {$order->shipping_city}";
        $lines[] = "Téléphone : {$order->shipping_phone}";

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode(implode("\n", $lines));
    }
}