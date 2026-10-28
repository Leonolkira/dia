<?php

$pickup = 'NZENG-AYONG, Carrefour GP';

return [
    'shipping_fee' => 2000,
    'free_shipping_from' => 50000,
    'deposit_rate' => 0.5,
    'whatsapp' => env('SHOP_WHATSAPP', '24160348225'),
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
    'pickup_label' => $pickup,
    'pickup_note' => 'Habituellement prête en 1 heure',
    'pickup_address' => 'Retrait en magasin : ' . $pickup,
    'pickup_city' => 'Libreville',
];