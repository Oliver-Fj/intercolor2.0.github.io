<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'price_at_time'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price_at_time' => 'decimal:2'
    ];

    /**
     * Obtener el usuario al que pertenece este item del carrito
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtener el producto asociado a este item del carrito
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Obtener el subtotal del item
     */
    public function getSubtotalAttribute()
    {
        return $this->quantity * $this->price_at_time;
    }
}
