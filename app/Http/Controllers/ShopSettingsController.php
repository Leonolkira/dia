<?php

namespace App\Http\Controllers;

class ShopSettingsController extends Controller
{
    public function __invoke()
    {
        return response()->json([
            'shipping_fee' => (int) config('shop.shipping_fee'),
            'free_shipping_from' => (int) config('shop.free_shipping_from'),
            'deposit_rate' => (float) config('shop.deposit_rate'),
            'whatsapp' => config('shop.whatsapp'),
            'pickup_label' => config('shop.pickup_label'),
            'pickup_note' => config('shop.pickup_note'),
        ]);
    }
}