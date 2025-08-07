<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MealPlan;
use App\Models\User;

class MealPlanPolicy
{
    /**
     * Determine whether the user can view any meal plans.
     */
    public function viewAny(User $user): bool
    {
        return true; // User can view their own meal plans
    }

    /**
     * Determine whether the user can view the meal plan.
     */
    public function view(User $user, MealPlan $mealPlan): bool
    {
        return $user->id === $mealPlan->user_id;
    }

    /**
     * Determine whether the user can create meal plans.
     */
    public function create(User $user): bool
    {
        return true; // Any authenticated user can create meal plans
    }

    /**
     * Determine whether the user can update the meal plan.
     */
    public function update(User $user, MealPlan $mealPlan): bool
    {
        // User must own the meal plan (status check happens in controller/request)
        return $user->id === $mealPlan->user_id;
    }

    /**
     * Determine whether the user can delete the meal plan.
     */
    public function delete(User $user, MealPlan $mealPlan): bool
    {
        // User must own the meal plan and it cannot be currently processing
        return $user->id === $mealPlan->user_id && $mealPlan->status !== 'processing';
    }

    /**
     * Determine whether the user can restore the meal plan.
     */
    public function restore(User $user, MealPlan $mealPlan): bool
    {
        return $user->id === $mealPlan->user_id;
    }

    /**
     * Determine whether the user can permanently delete the meal plan.
     */
    public function forceDelete(User $user, MealPlan $mealPlan): bool
    {
        return $user->id === $mealPlan->user_id && $mealPlan->status !== 'processing';
    }
}
