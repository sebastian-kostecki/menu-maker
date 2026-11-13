<?php

use App\Models\Recipe;
use App\Models\User;
use Tests\TestCase;

/**
 * This file configures Pest for the Laravel application test suite.
 */
uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Give the user the given number of recipes in every meal category.
 */
function seedFullWeekOfRecipes(User $user, int $perCategory = 7): void
{
    Recipe::factory()->count($perCategory)->breakfast()->for($user)->create();
    Recipe::factory()->count($perCategory)->supper()->for($user)->create();
    Recipe::factory()->count($perCategory)->dinner()->for($user)->create();
}
