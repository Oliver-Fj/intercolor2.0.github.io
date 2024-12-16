<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Slider extends Model
{
    protected $fillable = [
        'title',
        'image_url',
        'active',
        'order'
    ];

    protected $casts = [
        'active' => 'boolean',
        'order' => 'integer'
    ];

    public function groups()
    {
        return $this->belongsToMany(SliderGroup::class, 'slider_group_slider')
            ->withPivot('order')
            ->withPivot('order');
    }
}
