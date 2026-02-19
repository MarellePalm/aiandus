<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CalendarNote extends Model
{
    protected $fillable = [
        'user_id',
        'note_date',
        'title',
        'body',
        'media',
        'type',
        'done',
        'due_at',
    ];

    protected $casts = [
        'note_date' => 'date:Y-m-d',
        'media' => 'array',
        'done' => 'boolean',
        'due_at' => 'datetime',
    ];

    protected $appends = ['media_urls'];

    public function getMediaUrlsAttribute(): array
    {
        $paths = $this->media ?? [];
        return array_map(fn (string $path) => Storage::url($path), $paths);
    }
}