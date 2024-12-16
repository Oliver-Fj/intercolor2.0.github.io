<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array
     */

    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'stock',
        'status',
        'is_public',
        'category_id',
        'image_url',
        'color',
        'type',
        'featured'
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'is_public' => 'boolean',
        'price' => 'decimal:2',
        'featured' => 'boolean',
        'stock' => 'integer'
    ];

    /**
     * Las reglas de validación para los productos.
     *
     * @return array
     */
    public static function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'color' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'image_url' => 'required|string|max:255',
            'featured' => 'boolean',
            'stock' => 'required|integer|min:0'
        ];
    }

    /**
     * El producto puede tener varias categorías.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */

    public function stockHistories()
    {
        return $this->hasMany(StockHistory::class);
    }

    public function stockAlert()
    {
        return $this->hasOne(StockAlert::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_product');
    }
}
