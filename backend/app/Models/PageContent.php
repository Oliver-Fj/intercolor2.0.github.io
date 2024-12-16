<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageContent extends Model
{
    protected $fillable = [
        'page_name',
        'section_name',
        'title',
        'description',
        'image_url',
        'season',
        'is_active',
        'order',
        'additional_data'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'additional_data' => 'array',
        'order' => 'integer'
    ];
}
