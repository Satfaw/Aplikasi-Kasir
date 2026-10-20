<?php

namespace App\Models;

use App\Models\BaseUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Uom extends Model
{
    protected $fillable = [
        'code',
        'name',
        'base_unit_id',
        'symbol',
        'description',
        'is_active'
    ];

    public function base_unit(): BelongsTo
    {
        return $this->belongsTo(BaseUnit::class);
    }

    public function product(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
