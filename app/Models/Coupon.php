<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'type',
        'value',
        'min_purchase',
        'usage_limit',
        'used_count',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    // Vérifie si le coupon est encore valide
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    // Calcule la réduction pour un montant donné
    public function calculateDiscount(float $total): float
    {
        if ($this->type === 'pourcentage') {
            return round($total * ($this->value / 100), 2);
        }

        return min($this->value, $total); // ne peut pas dépasser le total
    }
}
