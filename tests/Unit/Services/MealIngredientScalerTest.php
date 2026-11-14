<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\MealIngredient;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use App\Services\IngredientScaling\AiRefinementFailedException;
use App\Services\IngredientScaling\IngredientRefiner;
use App\Services\IngredientScaling\MealIngredientScaler;
use App\Services\IngredientScaling\RefinementConfigurationException;
use App\Services\IngredientScaling\RefinementFailedException;
use App\Services\IngredientScaling\RefinementItem;
use App\Services\IngredientScaling\ScalingOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Sleep;

uses(RefreshDatabase::class);

function scalerPlan(User $user): MealPlan
{
    return MealPlan::factory()->for($user)->done()->create([
        'start_date' => '2026-11-16',
        'end_date' => '2026-11-22',
    ]);
}

/**
 * The worked example: woman 40 (2400) + man 40 (3000) + child 7 (1800) = 7200 kcal/day.
 */
function addWorkedExampleFamily(User $user): void
{
    FamilyMember::factory()->for($user)->create(['first_name' => 'Anna', 'gender' => 'female', 'birth_date' => '1986-11-16', 'weight_kg' => '65.0', 'height_cm' => '165.0']);
    FamilyMember::factory()->for($user)->create(['first_name' => 'Jan', 'gender' => 'male', 'birth_date' => '1986-11-16', 'weight_kg' => '75.0', 'height_cm' => '180.0']);
    FamilyMember::factory()->for($user)->child()->create(['first_name' => 'Ola', 'gender' => 'female', 'birth_date' => '2019-11-16']);
}

function addMealOf(MealPlan $plan, Recipe $recipe, string $date = '2026-11-16'): Meal
{
    return Meal::query()->create([
        'meal_plan_id' => $plan->id,
        'recipe_id' => $recipe->id,
        'meal_date' => $date,
        'meal_category' => $recipe->category,
    ]);
}

it('scales a meal by family kcal for that meal over recipe kcal (worked example, factor 2)', function (): void {
    $user = User::factory()->create();
    addWorkedExampleFamily($user);
    $recipe = Recipe::factory()->supper()->for($user)->create(['calories' => '1440']);
    attachIngredient($recipe, 'Mąka', '100', 'g');
    $plan = scalerPlan($user);
    $meal = addMealOf($plan, $recipe);

    $outcome = app(MealIngredientScaler::class)->scale($plan);

    $row = $meal->ingredients()->sole();
    expect($outcome->status)->toBe(ScalingOutcome::STATUS_REFINED)
        ->and($outcome->unscaledRecipes)->toBe([])
        ->and($outcome->membersWithoutBody)->toBe([])
        ->and($row->recipe_quantity)->toBe('100.00')
        ->and($row->computed_quantity)->toBe('200.00')
        ->and($row->quantity)->toBe('200.00')
        ->and($row->source)->toBe(MealIngredient::SOURCE_AI);
});

it('keeps the recipe amounts and reports as_written when the owner has no family', function (): void {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->supper()->for($user)->create(['calories' => '1440']);
    attachIngredient($recipe, 'Mąka', '100', 'g');
    $plan = scalerPlan($user);
    $meal = addMealOf($plan, $recipe);

    $outcome = app(MealIngredientScaler::class)->scale($plan);

    $row = $meal->ingredients()->sole();
    expect($outcome->status)->toBe(ScalingOutcome::STATUS_AS_WRITTEN)
        ->and($row->quantity)->toBe('100.00')
        ->and($row->source)->toBe(MealIngredient::SOURCE_AS_WRITTEN);
});

it('keeps a 0-kcal recipe as written and lists each name once, in Polish order', function (): void {
    $user = User::factory()->create();
    addWorkedExampleFamily($user);
    $zero = Recipe::factory()->supper()->for($user)->create(['name' => 'Żurek', 'calories' => '0']);
    $sameName = Recipe::factory()->dinner()->for($user)->create(['name' => 'Żurek', 'calories' => '0']);
    $other = Recipe::factory()->breakfast()->for($user)->create(['name' => 'Łazanki', 'calories' => '0']);
    attachIngredient($zero, 'Mąka', '100', 'g');
    $plan = scalerPlan($user);
    $meal = addMealOf($plan, $zero);
    addMealOf($plan, $zero, '2026-11-17');
    addMealOf($plan, $other, '2026-11-18');
    addMealOf($plan, $sameName, '2026-11-19');

    $outcome = app(MealIngredientScaler::class)->scale($plan);

    $row = $meal->ingredients()->sole();
    expect($outcome->status)->toBe(ScalingOutcome::STATUS_REFINED)
        ->and($outcome->unscaledRecipes)->toBe(['Łazanki', 'Żurek'])
        ->and($row->quantity)->toBe('100.00')
        ->and($row->source)->toBe(MealIngredient::SOURCE_AS_WRITTEN);
});

it('names adults without weight or height in membersWithoutBody', function (): void {
    $user = User::factory()->create();
    FamilyMember::factory()->for($user)->create(['first_name' => 'Marek', 'gender' => 'male', 'birth_date' => '1986-11-16', 'weight_kg' => null, 'height_cm' => null]);
    FamilyMember::factory()->for($user)->child()->create(['first_name' => 'Ola', 'birth_date' => '2019-11-16']);
    $plan = scalerPlan($user);

    $outcome = app(MealIngredientScaler::class)->scale($plan);

    expect($outcome->membersWithoutBody)->toBe(['Marek'])
        ->and($outcome->toArray())->toBe([
            'status' => 'refined',
            'unscaled_recipes' => [],
            'members_without_body' => ['Marek'],
        ]);
});

it("ignores another user's family members", function (): void {
    $user = User::factory()->create();
    addWorkedExampleFamily(User::factory()->create());
    $recipe = Recipe::factory()->supper()->for($user)->create(['calories' => '1440']);
    attachIngredient($recipe, 'Mąka', '100', 'g');
    $plan = scalerPlan($user);
    $meal = addMealOf($plan, $recipe);

    $outcome = app(MealIngredientScaler::class)->scale($plan);

    expect($outcome->status)->toBe(ScalingOutcome::STATUS_AS_WRITTEN)
        ->and($meal->ingredients()->sole()->quantity)->toBe('100.00');
});

it('replaces the rows on a second run and drops them with the meals (cascade)', function (): void {
    $user = User::factory()->create();
    addWorkedExampleFamily($user);
    $recipe = Recipe::factory()->supper()->for($user)->create(['calories' => '1440']);
    attachIngredient($recipe, 'Mąka', '100', 'g');
    attachIngredient($recipe, 'Mleko', '50', 'ml');
    $plan = scalerPlan($user);
    $meal = addMealOf($plan, $recipe);
    $scaler = app(MealIngredientScaler::class);

    $scaler->scale($plan);
    $scaler->scale($plan);

    expect(MealIngredient::query()->count())->toBe(2)
        ->and($meal->ingredients()->pluck('ingredient_id')->all())->toBe([
            Ingredient::resolveIdByName('Mąka'),
            Ingredient::resolveIdByName('Mleko'),
        ]);

    $plan->meals()->delete();

    expect(MealIngredient::query()->count())->toBe(0);
});

it('backfills existing meals with their recipe amounts as written', function (): void {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->supper()->for($user)->create();
    attachIngredient($recipe, 'Mąka', '100', 'g');
    attachIngredient($recipe, 'Mleko', '250', 'ml');
    $plan = scalerPlan($user);
    $meal = addMealOf($plan, $recipe);

    $migration = require database_path('migrations/2026_11_14_111711_create_meal_ingredients_table.php');
    $migration->down();
    $migration->up();

    $rows = MealIngredient::query()->where('meal_id', $meal->id)->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('quantity')->all())->toBe(['100.00', '250.00'])
        ->and($rows->pluck('recipe_quantity')->all())->toBe(['100.00', '250.00'])
        ->and($rows->pluck('source')->unique()->all())->toBe([MealIngredient::SOURCE_AS_WRITTEN])
        ->and(DB::table('meal_ingredients')->count())->toBe(2);
});

/**
 * Binds a refiner that runs the given steps in order: a Throwable is thrown, an array is returned (item key map), a closure receives the items.
 *
 * @param  list<Throwable|array<string, string>|Closure>  $steps
 */
function bindRefinerSteps(array $steps): object
{
    $refiner = new class($steps) implements IngredientRefiner
    {
        public int $calls = 0;

        /** @var list<list<RefinementItem>> */
        public array $received = [];

        /** @param list<Throwable|array<string, string>|Closure> $steps */
        public function __construct(private array $steps) {}

        public function refine(array $items): array
        {
            $this->calls++;
            $this->received[] = $items;
            $step = array_shift($this->steps);

            if ($step instanceof Throwable) {
                throw $step;
            }

            return $step instanceof Closure ? $step($items) : $step;
        }
    };

    app()->instance(IngredientRefiner::class, $refiner);

    return $refiner;
}

function supperPlanWithFlour(User $user, string $calories = '1440'): array
{
    addWorkedExampleFamily($user);
    $recipe = Recipe::factory()->supper()->for($user)->create(['calories' => $calories]);
    attachIngredient($recipe, 'Mąka', '100', 'g');

    return [scalerPlan($user), $recipe];
}

it('retries a failing refiner and succeeds on the third attempt without reporting', function (): void {
    Sleep::fake();
    Exceptions::fake();
    [$plan, $recipe] = supperPlanWithFlour(User::factory()->create());
    $meal = addMealOf($plan, $recipe);
    $refiner = bindRefinerSteps([
        new RefinementFailedException('boom'),
        new RefinementFailedException('boom'),
        fn (array $items): array => [$items[0]->key => '210'],
    ]);

    $outcome = app(MealIngredientScaler::class)->scale($plan);

    $row = $meal->ingredients()->sole();
    expect($refiner->calls)->toBe(3)
        ->and($outcome->status)->toBe(ScalingOutcome::STATUS_REFINED)
        ->and($row->quantity)->toBe('210.00')
        ->and($row->source)->toBe(MealIngredient::SOURCE_AI);
    Exceptions::assertNothingReported();
    Sleep::assertSleptTimes(2);
});

it('keeps computed amounts and reports exactly once after three failed attempts', function (): void {
    Sleep::fake();
    Exceptions::fake();
    [$plan, $recipe] = supperPlanWithFlour(User::factory()->create());
    $meal = addMealOf($plan, $recipe);
    $refiner = bindRefinerSteps(array_fill(0, 3, new RefinementFailedException('boom')));

    $outcome = app(MealIngredientScaler::class)->scale($plan);

    $row = $meal->ingredients()->sole();
    expect($refiner->calls)->toBe(3)
        ->and($outcome->status)->toBe(ScalingOutcome::STATUS_COMPUTED)
        ->and($row->quantity)->toBe('200.00')
        ->and($row->source)->toBe(MealIngredient::SOURCE_COMPUTED);
    Exceptions::assertReported(AiRefinementFailedException::class);
    Exceptions::assertReportedCount(1);
});

it('does not retry a configuration error and reports once', function (): void {
    Sleep::fake();
    Exceptions::fake();
    [$plan, $recipe] = supperPlanWithFlour(User::factory()->create());
    addMealOf($plan, $recipe);
    $refiner = bindRefinerSteps([new RefinementConfigurationException('no key')]);

    $outcome = app(MealIngredientScaler::class)->scale($plan);

    expect($refiner->calls)->toBe(1)
        ->and($outcome->status)->toBe(ScalingOutcome::STATUS_COMPUTED);
    Exceptions::assertReportedCount(1);
    Sleep::assertNeverSlept();
});

it('reverts an out-of-bounds item to computed while other items keep the AI amount', function (): void {
    $user = User::factory()->create();
    [$plan, $recipe] = supperPlanWithFlour($user);
    attachIngredient($recipe, 'Mleko', '50', 'ml');
    $meal = addMealOf($plan, $recipe);
    bindRefinerSteps([
        fn (array $items): array => collect($items)->mapWithKeys(
            fn (RefinementItem $item): array => [$item->key => $item->name === 'Mąka' ? '300' : '110'],
        )->all(),
    ]);

    app(MealIngredientScaler::class)->scale($plan);

    $rows = $meal->ingredients()->with('ingredient')->get()->keyBy(fn (MealIngredient $row): string => $row->ingredient->name);
    expect($rows['Mąka']->quantity)->toBe('200.00')
        ->and($rows['Mąka']->source)->toBe(MealIngredient::SOURCE_COMPUTED)
        ->and($rows['Mleko']->quantity)->toBe('110.00')
        ->and($rows['Mleko']->source)->toBe(MealIngredient::SOURCE_AI);
});

it('sends one item for the same ingredient, unit and computed quantity and applies the answer to both rows', function (): void {
    $user = User::factory()->create();
    [$plan, $recipe] = supperPlanWithFlour($user);
    $first = addMealOf($plan, $recipe);
    $second = addMealOf($plan, $recipe, '2026-11-17');
    $refiner = bindRefinerSteps([fn (array $items): array => [$items[0]->key => '190']]);

    app(MealIngredientScaler::class)->scale($plan);

    expect($refiner->received[0])->toHaveCount(1)
        ->and($first->ingredients()->sole()->quantity)->toBe('190.00')
        ->and($second->ingredients()->sole()->quantity)->toBe('190.00');
});

it('never calls the refiner when the owner has no family', function (): void {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->supper()->for($user)->create(['calories' => '1440']);
    attachIngredient($recipe, 'Mąka', '100', 'g');
    $plan = scalerPlan($user);
    addMealOf($plan, $recipe);
    $refiner = bindRefinerSteps([]);

    app(MealIngredientScaler::class)->scale($plan);

    expect($refiner->calls)->toBe(0);
});

it('never calls the refiner when only 0-kcal recipes were drawn and reports refined', function (): void {
    $user = User::factory()->create();
    [$plan, $recipe] = supperPlanWithFlour($user, '0');
    addMealOf($plan, $recipe);
    $refiner = bindRefinerSteps([]);

    $outcome = app(MealIngredientScaler::class)->scale($plan);

    expect($refiner->calls)->toBe(0)
        ->and($outcome->status)->toBe(ScalingOutcome::STATUS_REFINED);
});

it('does not put family data into the refiner items', function (): void {
    $user = User::factory()->create();
    [$plan, $recipe] = supperPlanWithFlour($user);
    addMealOf($plan, $recipe);
    $refiner = bindRefinerSteps([fn (array $items): array => [$items[0]->key => '200']]);

    app(MealIngredientScaler::class)->scale($plan);

    $payload = json_encode($refiner->received[0]);
    expect($payload)->not->toContain('Anna')->and($payload)->not->toContain('Jan')->and($payload)->not->toContain('Ola');
});
