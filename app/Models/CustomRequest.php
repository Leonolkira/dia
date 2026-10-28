<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomRequest extends Model
{
    public const STATUSES = ['en_attente', 'devis_envoye', 'acceptee', 'rejetee', 'annulee'];

    protected $fillable = [
        'user_id', 'product_id', 'order_id', 'title', 'description', 'size_info', 'color',
        'quantity', 'desired_date', 'images', 'status', 'quoted_price', 'lead_time_days',
        'admin_note', 'quoted_at','valid_until',
    ];

    protected $casts = [
        'images' => 'array',
        'desired_date' => 'date',
        'quoted_at' => 'datetime',
        'valid_until' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /** Adresses publiques des photos d'inspiration */
    public function getImageUrlsAttribute(): array
    {
        return collect($this->images ?? [])
            ->map(fn ($path) => '/storage/' . ltrim($path, '/'))
            ->all();
    }
}