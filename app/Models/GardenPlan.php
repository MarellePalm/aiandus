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
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function objects(): HasMany
    {
        return $this->hasMany(GardenObject::class);
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }
}
