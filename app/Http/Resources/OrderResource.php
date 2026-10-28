<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'delivery_mode' => $this->delivery_mode,
            'total' => $this->total,
            'discount' => $this->discount,
            'shipping_fee' => $this->shipping_fee,
            'deposit_amount' => $this->deposit_amount,
            'shipping_name' => $this->shipping_name,
            'shipping_address' => $this->shipping_address,
            'shipping_city' => $this->shipping_city,
            'shipping_phone' => $this->shipping_phone,
            'notes' => $this->notes,
            'created_at' => $this->created_at,

            'user' => $this->whenLoaded('user', fn () => $this->user
                ? ['id' => $this->user->id, 'name' => $this->user->name]
                : null),
            'vendor' => $this->whenLoaded('vendor', fn () => $this->vendor
                ? ['id' => $this->vendor->id, 'name' => $this->vendor->name]
                : null),

            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'image' => $item->relationLoaded('product')
                    ? $item->product?->images
                        ->sortBy(fn ($i) => [$i->color === null ? 0 : 1, $i->order])
                        ->first()?->public_url
                    : null,
            ])->values()),
        ];
    }
}