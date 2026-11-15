<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MealCategory;
use App\Models\FamilyMember;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\Unit;
use App\Models\User;
use App\Services\IngredientScaling\Drivers\FakeIngredientRefiner;
use App\Services\IngredientScaling\MealIngredientScaler;
use App\Services\MealPlanGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/**
 * Re-runnable fixtures for the Playwright specs: one planner user, one recipe-author (cook) user and one family-members user per browser project.
 *
 * The visual planner additionally owns four meal plans with pinned dates for the plans list; the done one starting
 * 2026-06-15 is reproducible (fixed recipe names and servings, scaled with the fake refiner), so the real meal plan
 * page can be screenshotted.
 */
class E2eMealPlanSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    public const EMAILS = [
        'e2e-planner-chromium@example.com',
        'e2e-planner-firefox@example.com',
        'e2e-planner-visual@example.com',
    ];

    /**
     * @var list<string>
     */
    public const COOK_EMAILS = [
        'e2e-cook-chromium@example.com',
        'e2e-cook-firefox@example.com',
    ];

    /**
     * @var list<string>
     */
    public const FAMILY_EMAILS = [
        'e2e-family-chromium@example.com',
        'e2e-family-firefox@example.com',
    ];

    public const PASSWORD = 'Password123!';

    public const VISUAL_EMAIL = 'e2e-planner-visual@example.com';

    public const VISUAL_PLAN_START = '2026-06-15';

    public const VISUAL_PLAN_END = '2026-06-21';

    /**
     * Fixed recipe names and servings of the visual user's plan, 7 per category in id order (the position is the day offset).
     *
     * @var array<string, list<array{name: string, servings: int}>>
     */
    public const VISUAL_RECIPES = [
        'breakfast' => [
            ['name' => 'Owsianka z jabłkiem i cynamonem', 'servings' => 2],
            ['name' => 'Jajecznica na maśle', 'servings' => 3],
            ['name' => 'Naleśniki z serem', 'servings' => 4],
            ['name' => 'Kanapki z pastą jajeczną', 'servings' => 2],
            ['name' => 'Placuszki bananowe', 'servings' => 3],
            ['name' => 'Jaglanka z owocami leśnymi i orzechami', 'servings' => 2],
            ['name' => 'Omlet z pieczarkami', 'servings' => 1],
        ],
        'supper' => [
            ['name' => 'Rosół z makaronem', 'servings' => 6],
            ['name' => 'Kotlet schabowy z ziemniakami i mizerią', 'servings' => 4],
            ['name' => 'Pierogi ruskie', 'servings' => 5],
            ['name' => 'Gulasz wołowy z kaszą gryczaną', 'servings' => 4],
            ['name' => 'Zupa pomidorowa z ryżem', 'servings' => 6],
            ['name' => 'Łosoś pieczony z warzywami', 'servings' => 2],
            ['name' => 'Bigos', 'servings' => 8],
        ],
        'dinner' => [
            ['name' => 'Sałatka grecka', 'servings' => 2],
            ['name' => 'Tosty z serem i szynką', 'servings' => 3],
            ['name' => 'Zapiekanka makaronowa', 'servings' => 4],
            ['name' => 'Twarożek ze szczypiorkiem', 'servings' => 2],
            ['name' => 'Krem z dyni z prażonymi pestkami', 'servings' => 4],
            ['name' => 'Sałatka z kurczakiem, rukolą i pomidorkami koktajlowymi', 'servings' => 3],
            ['name' => 'Pasta jajeczna na chrupkim pieczywie', 'servings' => 2],
        ],
    ];

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

            if ($email === self::VISUAL_EMAIL) {
                DB::transaction(fn () => $this->syncVisualMealPlans($user));
            }

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
            FamilyMember::where('user_id', $cook->id)->delete();
        }

        foreach (self::FAMILY_EMAILS as $email) {
            $familyUser = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => 'E2E Family',
                    'password' => Hash::make(self::PASSWORD),
                    'email_verified_at' => now(),
                ],
            );

            FamilyMember::where('user_id', $familyUser->id)->delete();
        }
    }

    /**
     * Give the visual user four meal plans with pinned dates and timestamps, so the plans list screenshots are stable.
     *
     * The "pending" status is deliberately absent: filtering by it yields the filtered empty state. The done plan
     * starting VISUAL_PLAN_START is built deterministically (see buildVisualPlan()), so the meal plan page can be
     * screenshotted too; the other done plan is a random draw, which the list does not show.
     */
    private function syncVisualMealPlans(User $user): void
    {
        $fixtures = [
            ['status' => 'done', 'start' => self::VISUAL_PLAN_START, 'end' => self::VISUAL_PLAN_END, 'created' => '2026-06-12 09:30:00'],
            ['status' => 'done', 'start' => '2026-06-08', 'end' => '2026-06-14', 'created' => '2026-06-05 18:15:00'],
            ['status' => 'processing', 'start' => '2026-06-22', 'end' => '2026-06-28', 'created' => '2026-06-02 12:00:00'],
            ['status' => 'error', 'start' => '2026-06-01', 'end' => '2026-06-07', 'created' => '2026-05-28 08:45:00'],
        ];

        foreach ($fixtures as $fixture) {
            $mealPlan = new MealPlan;
            $mealPlan->forceFill([
                'user_id' => $user->id,
                'start_date' => $fixture['start'],
                'end_date' => $fixture['end'],
                'status' => $fixture['status'],
                'created_at' => $fixture['created'],
                'updated_at' => $fixture['created'],
            ]);
            $mealPlan->saveQuietly();

            if ($fixture['start'] === self::VISUAL_PLAN_START) {
                $this->buildVisualPlan($user, $mealPlan);
            } elseif ($fixture['status'] === 'done') {
                app(MealPlanGenerator::class)->generate($mealPlan);
            }
        }
    }

    /**
     * Fill the visual done plan identically on every run: fixed recipes, fixed week, fake-scaled ingredients.
     *
     * Mirrors what GenerateMealPlanJob stores, without the queue, the random draw or any AI call.
     */
    private function buildVisualPlan(User $user, MealPlan $plan): void
    {
        foreach (self::VISUAL_RECIPES as $category => $fixed) {
            $recipes = Recipe::where('user_id', $user->id)
                ->where('category', $category)
                ->orderBy('id')
                ->limit(MealPlanGenerator::MEALS_PER_CATEGORY)
                ->get();

            foreach ($recipes as $offset => $recipe) {
                $recipe->update($fixed[$offset]);

                Meal::create([
                    'meal_plan_id' => $plan->id,
                    'recipe_id' => $recipe->id,
                    'meal_date' => $plan->start_date->copy()->addDays($offset),
                    'meal_category' => $category,
                ]);
            }
        }

        $outcome = app()->make(MealIngredientScaler::class, ['refiner' => new FakeIngredientRefiner])->scale($plan);

        // Keep the pinned updated_at: the plans list must not change between runs.
        $plan->timestamps = false;
        $plan->forceFill([
            'generation_meta' => [
                'generated_at' => $plan->start_date,
                'regenerate' => false,
                'version' => 'scaled-v1',
                'scaling' => $outcome->toArray(),
            ],
        ])->saveQuietly();
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
