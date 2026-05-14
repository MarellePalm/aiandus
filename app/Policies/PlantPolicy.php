<?php

namespace App\Policies;

use App\Models\Plant;
use App\Models\User;

class PlantPolicy
{
    public function view(User $user, Plant $plant): bool
    {
        return $user->id === $plant->user_id;
    }

    public function update(User $user, Plant $plant): bool
    {
        return $user->id === $plant->user_id;
    }

    public function delete(User $user, Plant $plant): bool
    {
        return $user->id === $plant->user_id;
    }

    public function water(User $user, Plant $plant): bool
    {
        return $user->id === $plant->user_id;
    }

    public function toggleFavorite(User $user, Plant $plant): bool
    {
        return $user->id === $plant->user_id;
    }
}
