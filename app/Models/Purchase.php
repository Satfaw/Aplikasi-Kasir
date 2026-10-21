<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = [
        'purchase_number',
        'user_id',
        'supplier_id',
        'purchase_date',
        'received_date',
        'total_payment',
        'status',
        'status_payment',
        'payment_method',
        'total_before_tax',
        'tax_rate',
        'tax_amount',
        'discount',
        'discount_amount',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseDetails(): HasMany
    {
        return $this->hasMany(PurchaseDetail::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::updated(function (Purchase $purchase) {
            if ($purchase->isDirty('status')) {
                $originalStatus = $purchase->getOriginal('status');

                // status berubah jadi received → tambah stok
                if ($purchase->status === 'received') {
                    foreach ($purchase->purchaseDetails as $detail) {
                        if ($detail->product) {
                            $detail->product->increment('stock', $detail->total_qty);
                        }
                    }
                }

                // status berubah dari received → cancelled → kurangi stok
                if ($originalStatus === 'received' && $purchase->status === 'cancelled') {
                    foreach ($purchase->purchaseDetails as $detail) {
                        if ($detail->product) {
                            $detail->product->decrement('stock', $detail->total_qty);
                        }
                    }
                }
            }
        });
    }
}
