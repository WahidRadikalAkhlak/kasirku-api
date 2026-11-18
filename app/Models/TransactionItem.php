<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionItem extends Model
{
    protected $fillable = [
        'transaction_id',
        'product_id',
        'product_name',
        'selected_variant',
        'qty',
        'price',
        'total_price',
    ];
}
