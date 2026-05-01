<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GardenObject extends Model
{
    protected $fillable = [
        'garden_plan_id',
        'type',
        'name',
        'x',
        'y',
        'width',
        'height',
        'meta',
    ];

    protected $casts = [
        'x' => 'integer',
        'y' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'meta' => 'array',
    ];

    public function gardenPlan(): BelongsTo
    {
        return $this->belongsTo(GardenPlan::class);
    }
}
