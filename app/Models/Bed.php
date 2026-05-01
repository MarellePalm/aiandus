<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bed extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'location',
        'image_url',
        'rows',
        'columns',
        'layout',
        'sort_order',
        'garden_x',
        'garden_y',
    ];

    protected $casts = [
        'layout' => 'array',
        'garden_x' => 'integer',
        'garden_y' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plants(): HasMany
    {
        return $this->hasMany(Plant::class);
    }
}
