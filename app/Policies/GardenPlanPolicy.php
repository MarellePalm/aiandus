<?php

namespace App\Policies;

use App\Models\GardenPlan;
use App\Models\User;

class GardenPlanPolicy
{
    public function view(User $user, GardenPlan $gardenPlan): bool
    {
        return $user->id === $gardenPlan->user_id;
    }

    public function update(User $user, GardenPlan $gardenPlan): bool
    {
        return $user->id === $gardenPlan->user_id;
    }

    public function delete(User $user, GardenPlan $gardenPlan): bool
    {
        return $user->id === $gardenPlan->user_id;
    }
}
