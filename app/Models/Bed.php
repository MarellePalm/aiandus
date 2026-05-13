<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bed extends Model
{
    protected $fillable = [
        'user_id',
        'garden_plan_id',
        'name',
        'location',
        'image_url',
        'rows',
        'columns',
        'layout',
        'sort_order',
        'garden_x',
        'garden_y',
        'cell_size_cm',
        'is_favorite',
    ];

    protected $casts = [
        'layout' => 'array',
        'garden_x' => 'integer',
        'garden_y' => 'integer',
        'cell_size_cm' => 'integer',
        'is_favorite' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gardenPlan(): BelongsTo
    {
        return $this->belongsTo(GardenPlan::class);
    }

    public function plants(): HasMany
    {
        return $this->hasMany(Plant::class);
    }
}
