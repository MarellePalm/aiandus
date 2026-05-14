<?php

namespace App\Policies;

use App\Models\Bed;
use App\Models\User;

class BedPolicy
{
    public function view(User $user, Bed $bed): bool
    {
        return $user->id === $bed->user_id;
    }

    public function update(User $user, Bed $bed): bool
    {
        return $user->id === $bed->user_id;
    }

    public function delete(User $user, Bed $bed): bool
    {
        return $user->id === $bed->user_id;
    }

    public function toggleFavorite(User $user, Bed $bed): bool
    {
        return $user->id === $bed->user_id;
    }
}
