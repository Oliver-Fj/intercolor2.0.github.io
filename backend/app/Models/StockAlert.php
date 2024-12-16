<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAlert extends Model
{
    protected $fillable = [
        'product_id',
        'minimum_stock',
        'is_active',
        'is_notified',
        'last_notification'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_notified' => 'boolean',
        'last_notification' => 'datetime'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}