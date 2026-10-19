<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'address',
        'cp_name',
        'cp_phone',
        'cp_email',
        'is_active',
    ];
}
