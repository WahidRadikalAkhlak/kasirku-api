<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'store_id',      // <-- WAJIB ADA DI SINI
        'business_type',
        'category',
        'barcode',
        'name',
        'price',
        'stock',
        'image_url',
        'variants',
    ];

    protected $casts = [
        'variants' => 'array',
        'price' => 'double',
    ];
}
