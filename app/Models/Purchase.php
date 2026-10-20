<?php

namespace App\Models;

use App\Models\Supplier;
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
}
