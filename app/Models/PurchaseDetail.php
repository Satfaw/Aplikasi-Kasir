<?php

namespace App\Models;

use App\Models\Product;
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
}
