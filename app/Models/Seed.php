<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seed extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'subtitle',
        'amount',
        'year',
        'expires_at',
        'image_url',
        'is_favorite',
        'notes',
    ];

    protected $casts = [
        'expires_at' => 'date',
        'is_favorite' => 'boolean',
    ];
}