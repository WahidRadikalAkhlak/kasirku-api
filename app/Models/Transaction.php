<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'store_id',
        'invoice_number',
        'business_type',
        'table_number',
        'subtotal',
        'tax_amount',
        'service_amount',
        'grand_total',
        'paid_amount',
        'change_amount',
        'payment_method',
        'payment_status',
        'qris_payload',
    ];

    public function items()
    {
        return $this->hasMany(TransactionItem::class);
    }
}
