<?php

namespace App\Policies;

use App\Models\CalendarNote;
use App\Models\User;

class CalendarNotePolicy
{
    public function view(User $user, CalendarNote $calendarNote): bool
    {
        return $user->id === $calendarNote->user_id;
    }

    public function update(User $user, CalendarNote $calendarNote): bool
    {
        return $user->id === $calendarNote->user_id;
    }

    public function delete(User $user, CalendarNote $calendarNote): bool
    {
        return $user->id === $calendarNote->user_id;
    }

    public function toggleDone(User $user, CalendarNote $calendarNote): bool
    {
        return $user->id === $calendarNote->user_id;
    }
}
