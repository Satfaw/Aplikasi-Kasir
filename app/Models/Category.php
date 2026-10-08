<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'image',
        'is_active',
    ];

    public function sub_category(): HasMany
    {
        return $this->hasMany(SubCategory::class);
    }
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
