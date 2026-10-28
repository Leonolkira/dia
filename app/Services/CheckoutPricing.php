<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Coupon;

class CheckoutPricing
{
    public const DEPOSIT_METHODS = ['airtel_money', 'moov_money'];

    public function subtotal(Cart $cart): float
    {
        $cart->loadMissing('items.product', 'items.variant');

        return (float) $cart->items->sum(fn ($item) =>
            ((float) $item->product->price + (float) ($item->variant->price_adjustment ?? 0)) * $item->quantity
        );
    }

    /** @return array{0: ?Coupon, 1: float} */
    public function applyCoupon(?string $code, float $subtotal, bool $lock = false): array
    {
        $code = trim((string) $code);
        if ($code === '') {
            return [null, 0.0];
        }

        $query = Coupon::where('code', $code);
        if ($lock) {
            $query->lockForUpdate();
        }
        $coupon = $query->first();

        if (!$coupon || !$coupon->isValid()) {
            throw new \DomainException('Coupon invalide ou expiré.');
        }
        if ($coupon->min_purchase && $subtotal < (float) $coupon->min_purchase) {
            throw new \DomainException(
                'Ce coupon nécessite un achat minimum de ' . number_format((float) $coupon->min_purchase, 0, ',', ' ') . ' FCFA.'
            );
        }

        return [$coupon, (float) $coupon->calculateDiscount($subtotal)];
    }

    public function totals(float $subtotal, float $discount, string $deliveryMode, ?string $paymentMethod): array
    {
        $net = max($subtotal - $discount, 0);

        $shipping = ($deliveryMode === 'retrait' || $net <= 0 || $net >= config('shop.free_shipping_from'))
            ? 0
            : (int) config('shop.shipping_fee');

        $total = $net + $shipping;
        $deposit = in_array($paymentMethod, self::DEPOSIT_METHODS, true)
            ? (int) ceil($total * config('shop.deposit_rate'))
            : 0;

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'shipping_fee' => $shipping,
            'total' => round($total, 2),
            'deposit_amount' => $deposit,
            'balance' => round($total - $deposit, 2),
        ];
    }
}