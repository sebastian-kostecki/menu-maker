<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MealCategory;
use App\Models\FamilyMember;
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
            $this->syncPinnedCalories($user);
            $this->syncFamily($user);

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

    /**
     * Pin recipe calories so that, with the fixed family, every meal scales by exactly 2.0.
     *
     * Family: 3000 + 2400 = 5400 kcal/day. Breakfast and Kolacja get 30% = 1620 (recipe 810), Obiad gets 40% = 2160 (recipe 1080).
     */
    private function syncPinnedCalories(User $user): void
    {
        $caloriesByCategory = [
            MealCategory::Breakfast->value => '810',
            MealCategory::Supper->value => '1080',
            MealCategory::Dinner->value => '810',
        ];

        foreach ($caloriesByCategory as $category => $calories) {
            Recipe::where('user_id', $user->id)
                ->where('category', $category)
                ->update(['calories' => $calories]);
        }
    }

    /**
     * Replace the user's family with a man (3000 kcal/day) and a woman (2400 kcal/day), both aged 40 with normal weight.
     */
    private function syncFamily(User $user): void
    {
        FamilyMember::where('user_id', $user->id)->delete();

        FamilyMember::create([
            'user_id' => $user->id,
            'first_name' => 'Jan',
            'birth_date' => now()->subYears(40)->toDateString(),
            'gender' => 'male',
            'weight_kg' => '75',
            'height_cm' => '180',
        ]);

        FamilyMember::create([
            'user_id' => $user->id,
            'first_name' => 'Anna',
            'birth_date' => now()->subYears(40)->toDateString(),
            'gender' => 'female',
            'weight_kg' => '65',
            'height_cm' => '165',
        ]);
    }
}
