<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Meal;
use App\Models\User;

class MealPolicy
{
    /**
     * Determine whether the user can update the meal: it belongs to whoever owns its plan
     * (the draft status check lives in the request).
     */
    public function update(User $user, Meal $meal): bool
    {
        return $meal->mealPlan->user_id === $user->id;
    }
}
