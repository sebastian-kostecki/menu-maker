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
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/**
 * Re-runnable fixtures for the Playwright specs: one planner user, one recipe-author (cook) user and one family-members user per browser project.
 *
 * The visual planner additionally owns four meal plans with pinned dates for the plans list; the done one starting
 * 2026-06-15 is reproducible (its pinned recipes, scaled with the fake refiner), so the real meal plan page can be
 * screenshotted. The dashboard-empty user always ends without recipes (trashed ones included), meal plans or family
 * members, so the dashboard shows its first-run state.
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

    public const DASHBOARD_EMPTY_EMAIL = 'e2e-dashboard-empty-visual@example.com';

    public const VISUAL_EMAIL = 'e2e-planner-visual@example.com';

    public const VISUAL_PLAN_START = '2026-06-15';

    public const VISUAL_DRAFT_START = '2026-06-29';

    public const VISUAL_PLAN_END = '2026-06-21';

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

            if ($email === self::VISUAL_EMAIL) {
                $this->syncVisualRecipes($user);
            } else {
                $this->topUpRecipes($user);
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

        $emptyUser = User::firstOrCreate(
            ['email' => self::DASHBOARD_EMPTY_EMAIL],
            [
                'name' => 'E2E Dashboard Empty',
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
            ],
        );

        $emptyUser->recipes()->withTrashed()->forceDelete();
        $emptyUser->mealPlans()->delete();
        FamilyMember::where('user_id', $emptyUser->id)->delete();
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
            ['status' => 'draft', 'start' => self::VISUAL_DRAFT_START, 'end' => '2026-07-05', 'created' => '2026-06-12 09:31:00'],
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
            } elseif ($fixture['status'] === 'draft') {
                // Wednesday's breakfast stays empty, so the visual draft shows the "Bez posiłku" cell.
                // Monday's Obiad (supper) is cooked once for Tuesday's, so the draft shows "Na 2 dni" and "Z wczoraj".
                $this->attachPinnedMeals($user, $mealPlan, [[MealCategory::Breakfast->value, 2]], [[MealCategory::Supper->value, 0]]);
            } elseif ($fixture['status'] === 'done') {
                app(MealPlanGenerator::class)->generate($mealPlan);
            }
        }
    }

    /**
     * Give the plan one pinned meal per category and day: the user's recipes in the visualRecipes() order, no scaling.
     *
     * @param  list<array{0: string, 1: int}>  $emptySlots  `[category, day offset]` pairs that get a meal without a recipe
     * @param  list<array{0: string, 1: int}>  $links  `[category, from offset]` pairs: the meal on the next day gets the source's recipe and the source feeds it
     */
    private function attachPinnedMeals(User $user, MealPlan $plan, array $emptySlots = [], array $links = []): void
    {
        /** @var array<string, Meal> $created */
        $created = [];

        foreach (MealCategory::values() as $category) {
            $recipes = Recipe::where('user_id', $user->id)
                ->where('category', $category)
                ->orderByDesc('created_at')
                ->limit(MealPlanGenerator::MEALS_PER_CATEGORY)
                ->get();

            foreach ($recipes as $offset => $recipe) {
                $created[$category.':'.$offset] = Meal::create([
                    'meal_plan_id' => $plan->id,
                    'recipe_id' => in_array([$category, $offset], $emptySlots, true) ? null : $recipe->id,
                    'meal_date' => $plan->start_date->copy()->addDays($offset),
                    'meal_category' => $category,
                ]);
            }
        }

        foreach ($links as [$category, $fromOffset]) {
            $source = $created[$category.':'.$fromOffset];
            $target = $created[$category.':'.($fromOffset + 1)];

            $target->update(['recipe_id' => $source->recipe_id]);
            $source->update(['feeds_meal_id' => $target->id]);
        }
    }

    /**
     * Fill the visual done plan identically on every run: the user's pinned recipes (syncVisualRecipes()), fixed
     * week, fake-scaled ingredients.
     *
     * Recipes are taken per category in the pinned `created_at desc` order (the visualRecipes() order), not by id:
     * syncVisualRecipes() may recreate rows, so ids differ between a fresh database and a re-seeded one.
     * Mirrors what GenerateMealPlanJob stores, without the queue, the random draw or any AI call.
     */
    private function buildVisualPlan(User $user, MealPlan $plan): void
    {
        $this->attachPinnedMeals($user, $plan);

        $outcome = app()->make(MealIngredientScaler::class, ['refiner' => new FakeIngredientRefiner])->scale($plan);

        // Keep the pinned updated_at: the plans list must not change between runs.
        $plan->timestamps = false;
        $plan->forceFill([
            'generation_meta' => [
                'generated_at' => $plan->start_date,
                'version' => 'scaled-v1',
                'scaling' => $outcome->toArray(),
            ],
        ])->saveQuietly();
    }

    /**
     * Create missing faker recipes so the user has enough of every category for a 21-meal draw.
     */
    private function topUpRecipes(User $user): void
    {
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
    }

    /**
     * Replace the visual user's recipes with the fixed list, so the recipe screenshots are identical everywhere.
     *
     * Every trashed recipe and every live one outside the list is force-deleted first (`recipes` has no unique
     * index on user and name, so a trashed twin would otherwise sit next to a live row). Timestamps are
     * written at 12:00 UTC on distinct days, newest first, so the default `created_at desc` order has no ties.
     */
    private function syncVisualRecipes(User $user): void
    {
        $recipes = self::visualRecipes();

        Recipe::onlyTrashed()->where('user_id', $user->id)->forceDelete();
        Recipe::where('user_id', $user->id)->whereNotIn('name', array_column($recipes, 'name'))->forceDelete();

        $newest = CarbonImmutable::create(2026, 5, 25, 12, 0, 0, 'UTC');

        Recipe::withoutTimestamps(function () use ($user, $recipes, $newest): void {
            foreach ($recipes as $index => $attributes) {
                $moment = $newest->subDays($index);

                $recipe = Recipe::firstOrNew(['user_id' => $user->id, 'name' => $attributes['name']]);
                $recipe->forceFill([
                    'category' => $attributes['category'],
                    'servings' => $attributes['servings'],
                    'instructions' => $attributes['instructions'],
                    'calories' => $recipe->calories ?? '810',
                    'created_at' => $moment,
                    'updated_at' => $moment,
                ])->save();
            }
        });
    }

    /**
     * Seven recipes per category, newest first.
     *
     * @return list<array{name: string, category: string, servings: int, instructions: string}>
     */
    public static function visualRecipes(): array
    {
        $breakfast = MealCategory::Breakfast->value;
        $supper = MealCategory::Supper->value;
        $dinner = MealCategory::Dinner->value;

        return [
            ['name' => 'Jajecznica ze szczypiorkiem', 'category' => $breakfast, 'servings' => 2, 'instructions' => "Rozpuść masło na patelni.\nWbij jajka, mieszaj do ścięcia.\nPosyp szczypiorkiem."],
            ['name' => 'Rosół z makaronem', 'category' => $supper, 'servings' => 4, 'instructions' => "Gotuj kurczaka z warzywami przez dwie godziny.\nPrzecedź wywar.\nPodaj z makaronem."],
            ['name' => 'Kanapki z pastą jajeczną', 'category' => $dinner, 'servings' => 2, 'instructions' => "Rozgnieć jajka z majonezem.\nSmaruj pieczywo pastą.\nUłóż rzodkiewkę."],
            ['name' => 'Owsianka z jabłkiem i cynamonem', 'category' => $breakfast, 'servings' => 1, 'instructions' => "Ugotuj płatki owsiane na mleku.\nDodaj starte jabłko.\nPosyp cynamonem."],
            ['name' => 'Kotlety mielone z ziemniakami', 'category' => $supper, 'servings' => 4, 'instructions' => "Uformuj kotlety z mięsa mielonego.\nSmaż po cztery minuty z każdej strony.\nPodaj z ziemniakami."],
            ['name' => 'Sałatka grecka z fetą', 'category' => $dinner, 'servings' => 2, 'instructions' => "Pokrój pomidory, ogórka i paprykę.\nDodaj oliwki i fetę.\nPolej oliwą."],
            ['name' => 'Naleśniki z serem', 'category' => $breakfast, 'servings' => 3, 'instructions' => "Usmaż cienkie naleśniki.\nWymieszaj twaróg z cukrem waniliowym.\nZawiń nadzienie w naleśniki."],
            ['name' => 'Zupa pomidorowa z ryżem', 'category' => $supper, 'servings' => 4, 'instructions' => "Ugotuj bulion z włoszczyzną.\nDodaj koncentrat i passatę.\nPodaj z ryżem."],
            ['name' => 'Tosty z awokado', 'category' => $dinner, 'servings' => 2, 'instructions' => "Opiecz kromki chleba.\nRozgnieć awokado z limonką.\nNałóż pastę na tosty."],
            ['name' => 'Jaglanka z owocami', 'category' => $breakfast, 'servings' => 2, 'instructions' => "Ugotuj kaszę jaglaną na mleku.\nDodaj miód.\nUdekoruj sezonowymi owocami."],
            ['name' => 'Gulasz wieprzowy z kaszą', 'category' => $supper, 'servings' => 5, 'instructions' => "Obsmaż mięso z cebulą.\nPodlej wodą i duś godzinę.\nPodaj z kaszą gryczaną."],
            ['name' => 'Zupa krem z dyni', 'category' => $dinner, 'servings' => 3, 'instructions' => "Upiecz dynię z cebulą.\nZmiksuj ze śmietanką.\nPosyp pestkami."],
            ['name' => 'Omlet z warzywami', 'category' => $breakfast, 'servings' => 1, 'instructions' => "Roztrzep jajka z mlekiem.\nSmaż z papryką i szpinakiem.\nZłóż na pół."],
            ['name' => 'Pierogi ruskie', 'category' => $supper, 'servings' => 4, 'instructions' => "Zagnieć ciasto na pierogi.\nPrzygotuj farsz z ziemniaków i sera.\nGotuj do wypłynięcia."],
            ['name' => 'Sałatka z tuńczykiem', 'category' => $dinner, 'servings' => 2, 'instructions' => "Wymieszaj sałatę z tuńczykiem.\nDodaj kukurydzę i pomidorki.\nSpryskaj sosem winegret."],
            ['name' => 'Twarożek z rzodkiewką', 'category' => $breakfast, 'servings' => 2, 'instructions' => "Wymieszaj twaróg ze śmietaną.\nDodaj posiekaną rzodkiewkę.\nPodaj z chlebem."],
            ['name' => 'Schabowy z surówką', 'category' => $supper, 'servings' => 4, 'instructions' => "Rozbij kotlety i obtocz w bułce tartej.\nSmaż na złoto.\nPodaj z surówką z kapusty."],
            ['name' => 'Zapiekanka z pieczarkami', 'category' => $dinner, 'servings' => 2, 'instructions' => "Ułóż pieczarki na bagietce.\nPosyp serem.\nZapiekaj dziesięć minut."],
            ['name' => 'Chia z mlekiem kokosowym', 'category' => $breakfast, 'servings' => 1, 'instructions' => "Zalej nasiona chia mlekiem kokosowym.\nOdstaw na noc do lodówki.\nDodaj mango."],
            ['name' => 'Makaron z pesto', 'category' => $supper, 'servings' => 3, 'instructions' => "Ugotuj makaron al dente.\nWymieszaj z pesto bazyliowym.\nPosyp parmezanem."],
            ['name' => 'Placki ziemniaczane ze śmietaną', 'category' => $dinner, 'servings' => 3, 'instructions' => "Zetrzyj ziemniaki z cebulą.\nDodaj jajko i mąkę.\nSmaż do zarumienienia."],
        ];
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

        Recipe::withoutTimestamps(function () use ($user, $caloriesByCategory): void {
            foreach ($caloriesByCategory as $category => $calories) {
                Recipe::where('user_id', $user->id)
                    ->where('category', $category)
                    ->update(['calories' => $calories]);
            }
        });
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
