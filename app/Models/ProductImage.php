<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'color', 'url', 'order'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
{
    return $this->belongsTo(ProductVariant::class, 'product_variant_id');
}

public function getPublicUrlAttribute(): string
{
    return '/storage/' . Str::after($this->url, '/storage/');
}

public function getStoragePathAttribute(): string
{
    return Str::after($this->url, '/storage/');
}
}
