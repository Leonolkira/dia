<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $avg = $this->getAttribute('reviews_avg_rating');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'compare_price' => $this->compare_price,
            'stock' => $this->stock,
            'available_stock' => $this->available_stock,
            'has_variants' => $this->has_variants,
            'sku' => $this->sku,
            'is_active' => (bool) $this->is_active,
            'on_order' => (bool) $this->on_order,
            'lead_time_days' => $this->lead_time_days,
            'details' => $this->details,

            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),

            'vendor' => $this->whenLoaded('vendor', fn () => $this->vendor
                ? ['id' => $this->vendor->id, 'name' => $this->vendor->name]
                : null),

            // Photos générales d'abord (donc images[0] = vignette du catalogue), puis par couleur
            'images' => $this->whenLoaded('images', fn () => $this->images
                ->sortBy(fn ($img) => [$img->color === null ? 0 : 1, $img->order])
                ->map(fn ($img) => [
                    'id' => $img->id,
                    'url' => $img->public_url,
                    'order' => $img->order,
                    'color' => $img->color,
                ])->values()),

            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn ($v) => [
                'id' => $v->id,
                'color' => $v->color,
                'size' => $v->size,
                'label' => $v->label,
                'price_adjustment' => $v->price_adjustment,
                'stock' => $v->stock,
                'sku' => $v->sku,
            ])->values()),

            'reviews' => $this->whenLoaded('reviews', fn () => $this->reviews->map(fn ($r) => [
    'id' => $r->id,
    'rating' => $r->rating,
    'title' => $r->title,
    'comment' => $r->comment,
    'created_at' => $r->created_at,
    'verified_purchase' => $r->order_id !== null,
    'user' => ['name' => $r->author_name],
])->values()),

            'average_rating' => $avg !== null ? round((float) $avg, 1) : null,
            'reviews_count' => $this->getAttribute('reviews_count') ?? 0,
        ];
    }
}