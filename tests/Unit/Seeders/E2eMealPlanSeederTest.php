<?php

declare(strict_types=1);

use App\Http\Resources\ShoppingListResource;
use App\Models\FamilyMember;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use App\Services\IngredientScaling\ScalingOutcome;
use App\Services\ShoppingList\ShoppingListBuilder;
use Database\Seeders\E2eMealPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // A real driver would call the AI; the seeder must use the fake refiner regardless.
    config(['services.ingredient_refiner.driver' => 'openrouter']);
    Http::preventStrayRequests();

    $this->seed(E2eMealPlanSeeder::class);
    $this->seed(E2eMealPlanSeeder::class);
});

it('gives the visual user the five list fixtures, with one deterministic done plan for the fixed week', function (): void {
    $user = User::where('email', E2eMealPlanSeeder::VISUAL_EMAIL)->firstOrFail();
    $plan = $user->mealPlans()->whereDate('start_date', E2eMealPlanSeeder::VISUAL_PLAN_START)->sole();

    expect($user->mealPlans()->orderByDesc('created_at')->pluck('status')->all())->toBe(['draft', 'done', 'done', 'processing', 'error'])
        ->and($plan->created_at->toDateTimeString())->toBe('2026-06-12 09:30:00')
        ->and($plan->updated_at->toDateTimeString())->toBe('2026-06-12 09:30:00');

    expect($plan->status)->toBe('done')
        ->and($plan->start_date->toDateString())->toBe('2026-06-15')
        ->and($plan->end_date->toDateString())->toBe('2026-06-21')
        ->and($plan->generation_meta['version'])->toBe('scaled-v1')
        // Only a successful refinement yields "refined"; a real driver without a key falls back to "computed".
        ->and($plan->generation_meta['scaling']['status'])->toBe(ScalingOutcome::STATUS_REFINED)
        ->and($plan->meals()->count())->toBe(21);

    $expected = [];
    foreach (E2eMealPlanSeeder::visualRecipes() as $recipe) {
        $expected[] = [$recipe['name'], $recipe['servings']];
    }

    $actual = $plan->meals()->with('recipe')->get()
        ->map(fn ($meal): array => [$meal->recipe->name, $meal->recipe->servings])
        ->all();

    expect($actual)->toEqualCanonicalizing($expected)
        ->and($user->recipes()->count())->toBe(21);
});

it('gives the visual user a pinned draft with 21 meals, one empty and one repeated, and no ingredients', function (): void {
    $user = User::where('email', E2eMealPlanSeeder::VISUAL_EMAIL)->firstOrFail();
    $draft = $user->mealPlans()->whereDate('start_date', E2eMealPlanSeeder::VISUAL_DRAFT_START)->sole();

    expect($draft->status)->toBe('draft')
        ->and($draft->created_at->toDateTimeString())->toBe('2026-06-12 09:31:00')
        ->and($draft->updated_at->toDateTimeString())->toBe('2026-06-12 09:31:00')
        ->and($draft->meals()->count())->toBe(21)
        ->and($draft->meals()->whereNotNull('recipe_id')->count())->toBe(20)
        ->and($draft->meals()->with('recipe')->get()->pluck('recipe.name')->filter()->unique()->count())->toBe(19)
        ->and(DB::table('meal_ingredients')->whereIn('meal_id', $draft->meals()->pluck('id'))->count())->toBe(0);
});

it('links Monday supper of the visual draft to Tuesday supper with the same recipe', function (): void {
    $draft = User::where('email', E2eMealPlanSeeder::VISUAL_EMAIL)->firstOrFail()->mealPlans()->whereDate('start_date', E2eMealPlanSeeder::VISUAL_DRAFT_START)->sole();
    $monday = $draft->meals()->where('meal_category', 'supper')->whereDate('meal_date', $draft->start_date->toDateString())->sole();
    $tuesday = $draft->meals()->where('meal_category', 'supper')->whereDate('meal_date', $draft->start_date->copy()->addDay()->toDateString())->sole();

    expect($monday->feeds_meal_id)->toBe($tuesday->id)
        ->and($monday->recipe_id)->not->toBeNull()
        ->and($tuesday->recipe_id)->toBe($monday->recipe_id)
        ->and($draft->meals()->whereNotNull('feeds_meal_id')->count())->toBe(1)
        ->and($draft->meals()->count())->toBe(21)
        ->and($draft->meals()->whereNotNull('recipe_id')->count())->toBe(20);
});

it('leaves Wednesday breakfast of the visual draft without a recipe', function (): void {
    $draft = User::where('email', E2eMealPlanSeeder::VISUAL_EMAIL)->firstOrFail()->mealPlans()->whereDate('start_date', E2eMealPlanSeeder::VISUAL_DRAFT_START)->sole();
    $empty = $draft->meals()->whereNull('recipe_id')->get();

    expect($empty)->toHaveCount(1)
        ->and($empty->first()->meal_category)->toBe('breakfast')
        ->and($empty->first()->meal_date->toDateString())->toBe($draft->start_date->copy()->addDays(2)->toDateString());
});

it('puts the recipes of each category on consecutive days from the plan start', function (): void {
    $plan = User::where('email', E2eMealPlanSeeder::VISUAL_EMAIL)->firstOrFail()->mealPlans()->whereDate('start_date', E2eMealPlanSeeder::VISUAL_PLAN_START)->sole();
    $days = array_map(
        fn (int $offset): string => $plan->start_date->copy()->addDays($offset)->toDateString(),
        range(0, 6),
    );

    // Each category takes its pinned recipes in list order (newest first), one per day.
    $byCategory = collect(E2eMealPlanSeeder::visualRecipes())->groupBy('category');

    foreach ($byCategory as $category => $recipes) {
        $meals = $plan->meals()->with('recipe')->where('meal_category', $category)->orderBy('meal_date')->get();

        expect($meals->map(fn ($meal): string => $meal->recipe->name)->all())->toBe($recipes->pluck('name')->all())
            ->and($meals->map(fn ($meal): string => $meal->meal_date->toDateString())->all())->toBe($days);
    }
});

it('displays the known shopping list for the visual plan', function (): void {
    $plan = User::where('email', E2eMealPlanSeeder::VISUAL_EMAIL)->firstOrFail()->mealPlans()->whereDate('start_date', E2eMealPlanSeeder::VISUAL_PLAN_START)->sole();

    $list = (new ShoppingListResource(app(ShoppingListBuilder::class)->forMealPlan($plan)))->resolve();

    expect(array_map(fn (array $item): array => [$item['name'], $item['display']], $list['items']))->toBe([
        ['Jajka', '42 szt.'],
        ['Mąka', '4,2 kg'],
        ['Mleko', '2,1 l'],
    ]);
});

it('leaves the other planners without plans', function (): void {
    foreach (['e2e-planner-chromium@example.com', 'e2e-planner-firefox@example.com'] as $email) {
        expect(MealPlan::where('user_id', User::where('email', $email)->value('id'))->count())->toBe(0);
    }
});

it('leaves the dashboard-empty user without recipes, plans and family members, however often it is seeded', function (): void {
    $user = User::where('email', E2eMealPlanSeeder::DASHBOARD_EMPTY_EMAIL)->firstOrFail();

    Recipe::factory()->for($user)->create();
    Recipe::factory()->for($user)->create()->delete();
    MealPlan::factory()->for($user)->create();
    FamilyMember::factory()->for($user)->create();

    $this->seed(E2eMealPlanSeeder::class);

    expect(User::where('email', E2eMealPlanSeeder::DASHBOARD_EMPTY_EMAIL)->count())->toBe(1)
        ->and(Recipe::withTrashed()->where('user_id', $user->id)->count())->toBe(0)
        ->and(MealPlan::where('user_id', $user->id)->count())->toBe(0)
        ->and(FamilyMember::where('user_id', $user->id)->count())->toBe(0);
});
