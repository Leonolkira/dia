<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'size_info' => $this->size_info,
            'color' => $this->color,
            'quantity' => $this->quantity,
            'desired_date' => $this->desired_date?->format('Y-m-d'),
            'images' => $this->image_urls,
            'status' => $this->status,
            'quoted_price' => $this->quoted_price,
            'lead_time_days' => $this->lead_time_days,
            'admin_note' => $this->admin_note,
            'quoted_at' => $this->quoted_at,
            'order_id' => $this->order_id,
            'created_at' => $this->created_at,

            'product' => $this->whenLoaded('product', fn () => $this->product
                ? ['id' => $this->product->id, 'name' => $this->product->name]
                : null),

            // Chargé uniquement par l'admin
            'user' => $this->whenLoaded('user', fn () => $this->user
                ? [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                ]
                : null),
                'valid_until' => $this->valid_until,
                'is_expired' => $this->is_expired,
        ];
    }
}