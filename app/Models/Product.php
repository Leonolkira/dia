<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'compare_price',
        'stock',
        'sku',
        'category_id',
        'user_id',
        'is_active',
    ];

    protected $casts = [
    'details' => 'array',
    'on_order' => 'boolean',
    'is_active' => 'boolean',
];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('order');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    // Pratique pour afficher la note moyenne facilement
    public function averageRating()
    {
        return $this->reviews()->avg('rating');
    }

    public function getHasVariantsAttribute(): bool
{
    return $this->variants->isNotEmpty();
}

// Règle de stock : avec variantes, le stock réel est la somme des variantes
public function getAvailableStockAttribute(): int
{
    return $this->has_variants
        ? (int) $this->variants->sum('stock')
        : (int) $this->stock;
}
}
