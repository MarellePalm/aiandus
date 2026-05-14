<?php

namespace App\Policies;

use App\Models\GardenObject;
use App\Models\User;

class GardenObjectPolicy
{
    public function view(User $user, GardenObject $gardenObject): bool
    {
        return $user->id === $gardenObject->gardenPlan->user_id;
    }

    public function update(User $user, GardenObject $gardenObject): bool
    {
        return $user->id === $gardenObject->gardenPlan->user_id;
    }

    public function delete(User $user, GardenObject $gardenObject): bool
    {
        return $user->id === $gardenObject->gardenPlan->user_id;
    }
}
