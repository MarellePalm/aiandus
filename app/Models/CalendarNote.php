<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarNote extends Model
{
    protected $fillable = [
        'user_id',
        'note_date',
        'title',
        'body',
    ];

    protected $casts = [
        'note_date' => 'date:Y-m-d',
    ];
}