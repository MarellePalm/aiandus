<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Plant extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'subtitle',
        'image_url',
        'notes',
        'tags',
        'last_watered_at'
    ];

    protected $casts = [
        'tags' => 'array',
        'last_watered_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this-> belongsTo(User::class);
    }

}
