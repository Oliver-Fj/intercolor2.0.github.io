<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SliderGroup extends Model
{
    protected $fillable = [
        'name',
        'description',
        'color',
        'active',
        'order'
    ];

    protected $casts = [
        'active' => 'boolean',
        'order' => 'integer'
    ];

    public function sliders(): BelongsToMany
    {
        return $this->belongsToMany(Slider::class, 'slider_group_slider')
                    ->withPivot('order')
                    ->orderBy('order');
    }
}
