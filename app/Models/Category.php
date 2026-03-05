<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function seeds(): HasMany
    {
        return $this->hasMany(Seed::class);
    }

    public function plants()
{
    return $this->hasMany(\App\Models\Plant::class);
}
}
