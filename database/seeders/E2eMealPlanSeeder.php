<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MealCategory;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Unit;
use App\Models\User;
use App\Services\MealPlanGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/**
 * Re-runnable fixtures for the Playwright specs: one planner user and one recipe-author (cook) user per browser project.
 */
class E2eMealPlanSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    public const EMAILS = [
        'e2e-planner-chromium@example.com',
        'e2e-planner-firefox@example.com',
    ];

    /**
     * @var list<string>
     */
    public const COOK_EMAILS = [
        'e2e-cook-chromium@example.com',
        'e2e-cook-firefox@example.com',
    ];

    public const PASSWORD = 'Password123!';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('E2eMealPlanSeeder must not run in production.');
        }

        foreach (self::EMAILS as $email) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => 'E2E Planner',
                    'password' => Hash::make(self::PASSWORD),
                    'email_verified_at' => now(),
                ],
            );

            $user->mealPlans()->delete();

            foreach (MealCategory::values() as $category) {
                $missing = MealPlanGenerator::MEALS_PER_CATEGORY
                    - Recipe::where('user_id', $user->id)->where('category', $category)->count();

                if ($missing > 0) {
                    Recipe::factory()->count($missing)->create([
                        'user_id' => $user->id,
                        'category' => $category,
                    ]);
                }
            }

            $this->syncFixedIngredients($user);

            RateLimiter::clear('generate-meal-plan:'.$user->id);
        }

        foreach (self::COOK_EMAILS as $email) {
            $cook = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => 'E2E Cook',
                    'password' => Hash::make(self::PASSWORD),
                    'email_verified_at' => now(),
                ],
            );

            $cook->recipes()->withTrashed()->forceDelete();
        }
    }

    /**
     * Give every recipe of the user the same ingredients, so any 21-meal draw yields one known shopping list.
     */
    private function syncFixedIngredients(User $user): void
    {
        $unitIds = Unit::query()->whereIn('code', ['g', 'ml', 'szt'])->pluck('id', 'code');

        $ingredients = [
            Ingredient::resolveIdByName('Mąka') => ['quantity' => '100', 'unit_id' => $unitIds['g']],
            Ingredient::resolveIdByName('Mleko') => ['quantity' => '50', 'unit_id' => $unitIds['ml']],
            Ingredient::resolveIdByName('Jajka') => ['quantity' => '1', 'unit_id' => $unitIds['szt']],
        ];

        Recipe::where('user_id', $user->id)->each(
            fn (Recipe $recipe) => $recipe->ingredients()->sync($ingredients),
        );
    }
}
