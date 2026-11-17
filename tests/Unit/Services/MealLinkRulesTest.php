<?php

declare(strict_types=1);

use App\Models\Meal;
use App\Models\Recipe;
use App\Models\User;
use App\Services\MealLinks\MealLinkRules;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Three Obiady on consecutive days of a draft: Monday, Tuesday and Wednesday.
 *
 * @return array{0: Meal, 1: Meal, 2: Meal}
 */
function threeObiady(User $user): array
{
    $recipes = Recipe::factory()->count(3)->supper()->for($user)->create();
    $plan = mealPlanWithRecipes($user, 'draft', ...$recipes);

    return $plan->meals()->orderBy('meal_date')->get()->all();
}

it('supper on day D can feed supper on day D+1', function (): void {
    [$monday, $tuesday] = threeObiady(User::factory()->create());

    expect((new MealLinkRules)->canFeed($monday, $tuesday))->toBeTrue();
});

it('canFeed refuses another category, a non-consecutive day, an empty source, another plan', function (): void {
    $user = User::factory()->create();
    [$monday, $tuesday, $wednesday] = threeObiady($user);
    $rules = new MealLinkRules;

    $breakfast = Meal::query()->create([
        'meal_plan_id' => $monday->meal_plan_id,
        'recipe_id' => Recipe::factory()->breakfast()->for($user)->create()->id,
        'meal_date' => $tuesday->meal_date,
        'meal_category' => 'breakfast',
    ]);
    [$otherMonday, $otherTuesday] = threeObiady($user);

    expect($rules->canFeed($monday, $breakfast))->toBeFalse()
        ->and($rules->canFeed($monday, $wednesday))->toBeFalse()
        ->and($rules->canFeed($tuesday, $monday))->toBeFalse()
        ->and($rules->canFeed($monday, $otherTuesday))->toBeFalse();

    $monday->update(['recipe_id' => null]);

    expect($rules->canFeed($monday->fresh(), $tuesday))->toBeFalse();
});

it('canFeed refuses chains in both directions', function (): void {
    [$monday, $tuesday, $wednesday] = threeObiady(User::factory()->create());
    $monday->update(['feeds_meal_id' => $tuesday->id]);
    $rules = new MealLinkRules;

    // Tuesday is carried over, so it cannot feed Wednesday.
    expect($rules->canFeed($tuesday->fresh(), $wednesday))->toBeFalse();

    // Wednesday cannot be fed by Tuesday, and Monday cannot be linked to a meal that already feeds another.
    $monday->update(['feeds_meal_id' => null]);
    $tuesday->update(['feeds_meal_id' => $wednesday->id]);

    expect($rules->canFeed($monday->fresh(), $tuesday->fresh()))->toBeFalse();
});

it('canFeed allows a re-link of the same pair and a source whose recipe is soft-deleted', function (): void {
    [$monday, $tuesday] = threeObiady(User::factory()->create());
    $monday->update(['feeds_meal_id' => $tuesday->id]);
    $rules = new MealLinkRules;

    expect($rules->canFeed($monday->fresh(), $tuesday))->toBeTrue();

    $monday->update(['feeds_meal_id' => null]);
    $monday->recipe->delete();

    expect($rules->canFeed($monday->fresh(), $tuesday))->toBeTrue();
});

it('names the carried-over meals and the servings of a cooked meal', function (): void {
    [$monday, $tuesday, $wednesday] = threeObiady(User::factory()->create());
    $monday->update(['feeds_meal_id' => $tuesday->id]);
    $tuesday->update(['recipe_id' => $monday->recipe_id]);
    $meals = [$monday->fresh(), $tuesday->fresh(), $wednesday->fresh()];
    $rules = new MealLinkRules;

    expect($rules->fedMealIds($meals))->toBe([$tuesday->id => true])
        ->and($rules->servingsFor($meals[0]))->toBe(2)
        ->and($rules->servingsFor($meals[1]))->toBe(1);
});

it('does not treat a fed meal with another recipe as carried over', function (): void {
    [$monday, $tuesday, $wednesday] = threeObiady(User::factory()->create());
    $monday->update(['feeds_meal_id' => $tuesday->id]);

    expect((new MealLinkRules)->fedMealIds([$monday->fresh(), $tuesday->fresh(), $wednesday->fresh()]))->toBe([]);
});

/**
 * Monday's Kolacja and Tuesday's Śniadanie of a draft, plus Monday's Obiad.
 *
 * @return array{0: Meal, 1: Meal, 2: Meal}
 */
function kolacjaAndSniadanie(User $user): array
{
    $plan = mealPlanWithRecipes($user, 'draft', Recipe::factory()->dinner()->for($user)->create(), Recipe::factory()->breakfast()->for($user)->create());
    [$kolacja, $sniadanie] = $plan->meals()->orderBy('meal_date')->get()->all();
    $obiad = Meal::query()->create([
        'meal_plan_id' => $plan->id,
        'recipe_id' => Recipe::factory()->supper()->for($user)->create()->id,
        'meal_date' => $kolacja->meal_date,
        'meal_category' => 'supper',
    ]);

    return [$kolacja, $sniadanie, $obiad];
}

it('dinner on day D can feed breakfast on day D+1', function (): void {
    [$kolacja, $sniadanie] = kolacjaAndSniadanie(User::factory()->create());

    expect((new MealLinkRules)->canFeed($kolacja, $sniadanie))->toBeTrue();
});

it('canFeed refuses supper to breakfast, dinner to dinner, breakfast to dinner, and dinner to breakfast on the same day or two days later', function (): void {
    $user = User::factory()->create();
    [$kolacja, $sniadanie, $obiad] = kolacjaAndSniadanie($user);
    $rules = new MealLinkRules;
    $make = fn (string $category, int $offset): Meal => Meal::query()->create([
        'meal_plan_id' => $kolacja->meal_plan_id,
        'recipe_id' => Recipe::factory()->{$category}()->for($user)->create()->id,
        'meal_date' => $kolacja->meal_date->copy()->addDays($offset),
        'meal_category' => $category,
    ]);

    $tuesdayKolacja = $make('dinner', 1);
    $sameDayBreakfast = $make('breakfast', 0);
    $wednesdayBreakfast = $make('breakfast', 2);

    expect($rules->canFeed($obiad, $sniadanie))->toBeFalse()
        ->and($rules->canFeed($kolacja, $tuesdayKolacja))->toBeFalse()
        ->and($rules->canFeed($sniadanie, $tuesdayKolacja))->toBeFalse()
        ->and($rules->canFeed($kolacja, $sameDayBreakfast))->toBeFalse()
        ->and($rules->canFeed($kolacja, $wednesdayBreakfast))->toBeFalse();
});

it('each category is the source of at most one pair and the target of at most one', function (): void {
    $from = array_count_values(array_column(MealLinkRules::PAIRS, 'from'));
    $to = array_count_values(array_column(MealLinkRules::PAIRS, 'to'));

    expect(max($from))->toBe(1)
        ->and(max($to))->toBe(1);
});

it('names the cooked and carried labels of each pair by category', function (): void {
    $rules = new MealLinkRules;

    expect($rules->cookedPairFor('supper')['cooked_label'])->toBe('Na 2 dni')
        ->and($rules->carriedPairFor('supper')['carried_label'])->toBe('Z wczoraj')
        ->and($rules->cookedPairFor('supper')['use_suffix'])->toBe('na 2 dni')
        ->and($rules->cookedPairFor('dinner')['cooked_label'])->toBe('Też na śniadanie')
        ->and($rules->carriedPairFor('breakfast')['carried_label'])->toBe('Z kolacji')
        ->and($rules->cookedPairFor('dinner')['use_suffix'])->toBe('też na śniadanie')
        ->and($rules->cookedPairFor('breakfast'))->toBeNull()
        ->and($rules->carriedPairFor('dinner'))->toBeNull();
});
