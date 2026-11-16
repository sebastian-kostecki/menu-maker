<?php

declare(strict_types=1);

use App\Enums\MealCategory;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use App\Services\InsufficientRecipesException;
use App\Services\MealPlanGenerator;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function planFor(User $user): MealPlan
{
    return MealPlan::factory()->for($user)->pending()->create([
        'start_date' => now()->startOfDay(),
        'end_date' => now()->startOfDay()->copy()->addDays(6),
    ]);
}

it('creates 21 meals, 7 per category', function (): void {
    $user = User::factory()->create();
    seedFullWeekOfRecipes($user, 10);
    $mealPlan = planFor($user);

    app(MealPlanGenerator::class)->generate($mealPlan);

    expect(Meal::query()->where('meal_plan_id', $mealPlan->id)->count())->toBe(21);

    foreach (MealCategory::values() as $category) {
        expect($mealPlan->meals()->where('meal_category', $category)->count())->toBe(7);
    }
});

it('uses 7 distinct recipes of the matching category and covers each day once per category', function (): void {
    $user = User::factory()->create();
    seedFullWeekOfRecipes($user, 10);
    $mealPlan = planFor($user);

    app(MealPlanGenerator::class)->generate($mealPlan);

    foreach (MealCategory::values() as $category) {
        $meals = $mealPlan->meals()->where('meal_category', $category)->get();

        expect($meals->pluck('recipe_id')->unique())->toHaveCount(7)
            ->and(Recipe::query()->whereIn('id', $meals->pluck('recipe_id'))->pluck('category')->unique()->all())->toBe([$category])
            ->and($meals->map(fn (Meal $meal) => $meal->meal_date->toDateString())->sort()->values()->all())
            ->toBe(collect(range(0, 6))->map(fn (int $day) => $mealPlan->start_date->copy()->addDays($day)->toDateString())->all());
    }
});

it('uses only the plan owner\'s recipes', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    seedFullWeekOfRecipes($owner);
    seedFullWeekOfRecipes($other, 20);
    $mealPlan = planFor($owner);

    app(MealPlanGenerator::class)->generate($mealPlan);

    $userIds = Recipe::query()->whereIn('id', $mealPlan->meals()->pluck('recipe_id'))->pluck('user_id')->unique()->all();
    expect($userIds)->toBe([$owner->id]);
});

it('excludes soft-deleted recipes', function (): void {
    $user = User::factory()->create();
    seedFullWeekOfRecipes($user, 9);
    $deleted = Recipe::forUser($user->id)->byCategory('dinner')->limit(2)->get();
    $deleted->each->delete();
    $mealPlan = planFor($user);

    app(MealPlanGenerator::class)->generate($mealPlan);

    expect($mealPlan->meals()->whereIn('recipe_id', $deleted->pluck('id'))->count())->toBe(0)
        ->and($mealPlan->meals()->count())->toBe(21);
});

it('replaces existing meals on regeneration', function (): void {
    $user = User::factory()->create();
    seedFullWeekOfRecipes($user, 9);
    $mealPlan = planFor($user);
    $generator = app(MealPlanGenerator::class);

    $generator->generate($mealPlan);
    $oldIds = $mealPlan->meals()->pluck('id');
    $generator->generate($mealPlan);

    expect($mealPlan->meals()->count())->toBe(21)
        ->and(Meal::query()->whereIn('id', $oldIds)->count())->toBe(0);
});

it('a category with 5 recipes fills 5 distinct days and leaves 2 empty', function (): void {
    $user = User::factory()->create();
    Recipe::factory()->count(7)->breakfast()->for($user)->create();
    Recipe::factory()->count(7)->supper()->for($user)->create();
    Recipe::factory()->count(5)->dinner()->for($user)->create();
    $mealPlan = planFor($user);

    app(MealPlanGenerator::class)->generate($mealPlan);

    $dinners = $mealPlan->meals()->where('meal_category', 'dinner')->get();
    $expectedDates = collect(range(0, 6))->map(fn (int $day) => $mealPlan->start_date->copy()->addDays($day)->toDateString())->all();

    expect($mealPlan->meals()->count())->toBe(21)
        ->and($dinners)->toHaveCount(7)
        ->and($dinners->whereNotNull('recipe_id')->pluck('recipe_id')->unique())->toHaveCount(5)
        ->and($dinners->whereNull('recipe_id'))->toHaveCount(2);

    foreach (['breakfast', 'dinner', 'supper'] as $category) {
        $dates = $mealPlan->meals()->where('meal_category', $category)->get()
            ->map(fn (Meal $meal) => $meal->meal_date->toDateString())->sort()->values()->all();

        expect($dates)->toBe($expectedDates);
    }
});

it('the days left empty are shuffled, not always the last ones', function (): void {
    $user = User::factory()->create();
    Recipe::factory()->count(7)->breakfast()->for($user)->create();
    Recipe::factory()->count(7)->supper()->for($user)->create();
    Recipe::factory()->count(5)->dinner()->for($user)->create();
    $mealPlan = planFor($user);

    $emptyDayOffsets = collect(range(1, 12))->flatMap(function () use ($mealPlan): array {
        app(MealPlanGenerator::class)->generate($mealPlan);

        return $mealPlan->meals()->where('meal_category', 'dinner')->whereNull('recipe_id')->get()
            ->map(fn (Meal $meal): int => (int) $mealPlan->start_date->diffInDays($meal->meal_date))->all();
    })->unique();

    expect($emptyDayOffsets->count())->toBeGreaterThan(2);
});

it('a category without recipes gets 7 empty meals', function (): void {
    $user = User::factory()->create();
    Recipe::factory()->count(7)->breakfast()->for($user)->create();
    $mealPlan = planFor($user);

    app(MealPlanGenerator::class)->generate($mealPlan);

    expect($mealPlan->meals()->count())->toBe(21)
        ->and($mealPlan->meals()->where('meal_category', 'breakfast')->whereNotNull('recipe_id')->count())->toBe(7)
        ->and($mealPlan->meals()->where('meal_category', 'supper')->whereNull('recipe_id')->count())->toBe(7)
        ->and($mealPlan->meals()->where('meal_category', 'dinner')->whereNull('recipe_id')->count())->toBe(7);
});

it('generate refuses a user without recipes and keeps the old meals', function (): void {
    $user = User::factory()->create();
    seedFullWeekOfRecipes($user);
    $mealPlan = planFor($user);
    $generator = app(MealPlanGenerator::class);
    $generator->generate($mealPlan);
    $before = $mealPlan->meals()->pluck('id')->sort()->values()->all();

    Recipe::forUser($user->id)->get()->each->delete();

    expect(fn () => $generator->generate($mealPlan))
        ->toThrow(InsufficientRecipesException::class, MealPlanGenerator::NO_RECIPES_MESSAGE)
        ->and($generator->hasAnyRecipe($user->id))->toBeFalse()
        ->and($mealPlan->meals()->pluck('id')->sort()->values()->all())->toBe($before);
});

it('recipes deleted after the check leave gaps, all deleted rolls back', function (): void {
    $user = User::factory()->create();
    seedFullWeekOfRecipes($user);
    $mealPlan = planFor($user);
    $generator = app(MealPlanGenerator::class);
    $generator->generate($mealPlan);

    $deleteOnce = function (callable $delete): void {
        $done = false;
        Event::listen(TransactionBeginning::class, function () use ($delete, &$done): void {
            if (! $done) {
                $done = true;
                $delete();
            }
        });
    };

    $deleteOnce(fn () => Recipe::forUser($user->id)->byCategory('supper')->first()->delete());
    $generator->generate($mealPlan);

    expect($mealPlan->meals()->count())->toBe(21)
        ->and($mealPlan->meals()->where('meal_category', 'supper')->whereNull('recipe_id')->count())->toBe(1);

    $afterGap = $mealPlan->meals()->pluck('id')->sort()->values()->all();

    $deleteOnce(fn () => Recipe::forUser($user->id)->get()->each->delete());

    expect(fn () => $generator->generate($mealPlan))->toThrow(InsufficientRecipesException::class)
        ->and($mealPlan->meals()->pluck('id')->sort()->values()->all())->toBe($afterGap);
});

it('recipe counts per category include every category', function (): void {
    $user = User::factory()->create();
    Recipe::factory()->count(3)->supper()->for($user)->create();
    Recipe::factory()->count(2)->supper()->for($user)->create()->each->delete();
    Recipe::factory()->count(4)->breakfast()->for(User::factory()->create())->create();

    expect(app(MealPlanGenerator::class)->recipeCountsPerCategory($user->id))
        ->toBe(['breakfast' => 0, 'supper' => 3, 'dinner' => 0]);
});
