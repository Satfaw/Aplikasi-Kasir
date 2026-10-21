<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseDetail extends Model
{
    protected $fillable = [
        'product_id',
        'purchase_id',
        'purchase_unit',
        'conversion',
        'qty',
        'total_qty',
        'price',
        'subtotal',
        'base_unit',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted(): void
    {
        // Saat detail dibuat, cek apakah purchase sudah berstatus received
        static::created(function (PurchaseDetail $detail) {
            if ($detail->purchase?->status === 'received' && $detail->product) {
                $detail->product->increment('stock', $detail->total_qty);
            }
        });

        // Saat detail dihapus dari purchase yang received, kurangi stok
        static::deleted(function (PurchaseDetail $detail) {
            if ($detail->purchase?->status === 'received' && $detail->product) {
                $detail->product->decrement('stock', $detail->total_qty);
            }
        });
    }
}
