<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'address',
        'business_type',
        'tax_percent',
        'service_charge',
        'primary_color',
        'logo_url',
    ];
}
