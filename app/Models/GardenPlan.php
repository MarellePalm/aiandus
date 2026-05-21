<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GardenPlan extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'width',
        'height',
        'unit',
        'shape_mask',
        'center_lat',
        'center_lng',
        'boundary_polygon',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'shape_mask' => 'array',
        'center_lat' => 'float',
        'center_lng' => 'float',
        'boundary_polygon' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }
}
