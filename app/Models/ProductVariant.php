<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'color', 'size', 'price_adjustment', 'stock', 'sku'];

// "Bleu / 40", "Rouge", "M"...
public function getLabelAttribute(): string
{
    return collect([$this->color, $this->size])->filter()->implode(' / ');
}
}
