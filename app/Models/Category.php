<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    public const SCOPE_PLANT = 'plant';

    public const SCOPE_SEED = 'seed';

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'scope',
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
        return $this->hasMany(Plant::class);
    }
}
