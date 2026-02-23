<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'image',
        'count',
        'is_favorite',
    ];

    protected $casts = [
        'count' => 'integer',
        'is_favorite' => 'boolean',
    ];
}
