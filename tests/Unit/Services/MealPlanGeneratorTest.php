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

it('reports the shortfall per category with Polish messages', function (): void {
    $user = User::factory()->create();
    Recipe::factory()->count(7)->supper()->for($user)->create();
    Recipe::factory()->count(4)->dinner()->for($user)->create();
    $generator = app(MealPlanGenerator::class);

    $missing = $generator->missingRecipesPerCategory($user->id);
    $messages = $generator->shortfallMessages($missing);

    expect($missing)->toBe(['breakfast' => 7, 'dinner' => 3])
        ->and($messages)->toHaveCount(2)
        ->and($messages[1])->toBe('Za mało przepisów w kategorii Kolacja: masz 4, potrzeba 7. Dodaj jeszcze 3.');
});

it('returns an empty shortfall when the user has enough recipes', function (): void {
    $user = User::factory()->create();
    seedFullWeekOfRecipes($user);

    expect(app(MealPlanGenerator::class)->missingRecipesPerCategory($user->id))->toBe([]);
});

it('throws on shortfall and leaves existing meals untouched', function (): void {
    $user = User::factory()->create();
    seedFullWeekOfRecipes($user);
    $mealPlan = planFor($user);
    $generator = app(MealPlanGenerator::class);
    $generator->generate($mealPlan);
    $before = $mealPlan->meals()->pluck('id')->sort()->values()->all();

    Recipe::forUser($user->id)->byCategory('dinner')->first()->delete();

    try {
        $generator->generate($mealPlan);
        $this->fail('Expected InsufficientRecipesException');
    } catch (InsufficientRecipesException $e) {
        expect($e->missing)->toBe(['dinner' => 1])
            ->and($e->getMessage())->toContain('Kolacja');
    }

    expect($mealPlan->meals()->pluck('id')->sort()->values()->all())->toBe($before);
});

it('rolls back and throws when a recipe is deleted between the check and the draw', function (): void {
    $user = User::factory()->create();
    seedFullWeekOfRecipes($user);
    $mealPlan = planFor($user);
    $generator = app(MealPlanGenerator::class);
    $generator->generate($mealPlan);
    $before = $mealPlan->meals()->pluck('id')->sort()->values()->all();

    $deleted = false;
    Event::listen(TransactionBeginning::class, function () use ($user, &$deleted): void {
        if (! $deleted) {
            $deleted = true;
            Recipe::forUser($user->id)->byCategory('supper')->first()->delete();
        }
    });

    expect(fn () => $generator->generate($mealPlan))
        ->toThrow(InsufficientRecipesException::class, 'Obiad')
        ->and($mealPlan->meals()->pluck('id')->sort()->values()->all())->toBe($before);
});
