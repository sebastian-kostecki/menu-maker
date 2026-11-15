<?php

declare(strict_types=1);

use App\Http\Resources\ShoppingListResource;
use App\Models\MealPlan;
use App\Models\User;
use App\Services\IngredientScaling\ScalingOutcome;
use App\Services\ShoppingList\ShoppingListBuilder;
use Database\Seeders\E2eMealPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // A real driver would call the AI; the seeder must use the fake refiner regardless.
    config(['services.ingredient_refiner.driver' => 'openrouter']);
    Http::preventStrayRequests();

    $this->seed(E2eMealPlanSeeder::class);
    $this->seed(E2eMealPlanSeeder::class);
});

it('gives the visual user exactly one done plan with the fixed week', function (): void {
    $user = User::where('email', E2eMealPlanSeeder::VISUAL_EMAIL)->firstOrFail();
    $plan = $user->mealPlans()->sole();

    expect($plan->status)->toBe('done')
        ->and($plan->start_date->toDateString())->toBe('2026-06-15')
        ->and($plan->end_date->toDateString())->toBe('2026-06-21')
        ->and($plan->generation_meta['version'])->toBe('scaled-v1')
        // Only a successful refinement yields "refined"; a real driver without a key falls back to "computed".
        ->and($plan->generation_meta['scaling']['status'])->toBe(ScalingOutcome::STATUS_REFINED)
        ->and($plan->meals()->count())->toBe(21);

    $expected = [];
    foreach (E2eMealPlanSeeder::VISUAL_RECIPES as $recipes) {
        foreach ($recipes as $recipe) {
            $expected[] = [$recipe['name'], $recipe['servings']];
        }
    }

    $actual = $plan->meals()->with('recipe')->get()
        ->map(fn ($meal): array => [$meal->recipe->name, $meal->recipe->servings])
        ->all();

    expect($actual)->toEqualCanonicalizing($expected)
        ->and($user->recipes()->count())->toBe(21);
});

it('puts the recipes of each category on consecutive days from the plan start', function (): void {
    $plan = User::where('email', E2eMealPlanSeeder::VISUAL_EMAIL)->firstOrFail()->mealPlans()->sole();
    $days = array_map(
        fn (int $offset): string => $plan->start_date->copy()->addDays($offset)->toDateString(),
        range(0, 6),
    );

    foreach (E2eMealPlanSeeder::VISUAL_RECIPES as $category => $recipes) {
        $meals = $plan->meals()->with('recipe')->where('meal_category', $category)->orderBy('meal_date')->get();

        expect($meals->map(fn ($meal): string => $meal->recipe->name)->all())->toBe(array_column($recipes, 'name'))
            ->and($meals->map(fn ($meal): string => $meal->meal_date->toDateString())->all())->toBe($days);
    }
});

it('displays the known shopping list for the visual plan', function (): void {
    $plan = User::where('email', E2eMealPlanSeeder::VISUAL_EMAIL)->firstOrFail()->mealPlans()->sole();

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
