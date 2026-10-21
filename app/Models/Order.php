<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

class Order extends Model
{
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected $fillable = [
        'customer_id',
        'date',
        'total_price',
        'tax_rate',
        'tax_amount',
        'discount',
        'discount_amount',
        'total_payment',
        'status',
        'payment_method',
        'payment_status',
    ];

    public function orderdetail(): HasMany
    {
        return $this->hasMany(OrderDetail::class);
    }

    #[Override]
    protected static function booted()
    {
        static::created(function ($order) {
            if ($order->status === 'completed') {
                foreach ($order->orderdetail as $detail) {
                    $product = $detail->product;

                    if ($product) {
                        $product->decrement('stock', $detail->qty);
                    }
                }
            }
        });

        static::updated(function ($order) {
            $originalStatus = $order->getOriginal('status');
            if ($order->isDirty('status') && $order->status === 'completed') {
                foreach ($order->orderdetail as $detail) {
                    $product = $detail->product;

                    if ($product) {
                        $product->decrement('stock', $detail->qty);
                    }
                }
            }

            if ($order->isDirty('status') && $originalStatus === 'completed' && $order->status === 'cancelled') {
                foreach ($order->orderdetail as $detail) {
                    $product = $detail->product;

                    if ($product) {
                        $product->increment('stock', $detail->qty);
                    }
                }
            }
        });
    }
}
