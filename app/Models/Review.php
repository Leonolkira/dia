<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = ['product_id', 'user_id', 'order_id', 'rating', 'comment', 'title'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function getAuthorNameAttribute(): string
{
    $name = trim((string) $this->user?->name);

    if ($name === '') {
        return 'Utilisateur supprimé';
    }

    $parts = preg_split('/\s+/', $name);

    return count($parts) > 1
        ? $parts[0] . ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.'
        : $parts[0];
}
}
