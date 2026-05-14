<?php

namespace App\Policies;

use App\Models\Seed;
use App\Models\User;

class SeedPolicy
{
    public function view(User $user, Seed $seed): bool
    {
        return $user->id === $seed->user_id;
    }

    public function update(User $user, Seed $seed): bool
    {
        return $user->id === $seed->user_id;
    }

    public function delete(User $user, Seed $seed): bool
    {
        return $user->id === $seed->user_id;
    }

    public function toggleFavorite(User $user, Seed $seed): bool
    {
        return $user->id === $seed->user_id;
    }
}
