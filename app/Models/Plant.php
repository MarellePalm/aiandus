<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Plant extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'subtitle',
        'planted_at',
        'image_url',
        'status',
        'notes',
        'tags',
        'last_watered_at',
];

    protected $casts = [
        'tags' => 'array',
        'last_watered_at' => 'datetime',
        'planted_at' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
