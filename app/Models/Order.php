<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
    'user_id', 'vendor_id', 'coupon_id', 'status', 'payment_method', 'payment_status',
    'delivery_mode', 'shipping_name', 'total', 'discount', 'shipping_fee', 'deposit_amount',
    'shipping_address', 'shipping_city', 'shipping_phone', 'notes',
];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }
}
