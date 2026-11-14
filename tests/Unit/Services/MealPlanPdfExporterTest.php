<?php

declare(strict_types=1);

use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use App\Services\IngredientScaling\MealIngredientScaler;
use App\Services\MealPlanPdf\MealPlanPdfExporter;
use App\Services\ShoppingList\ShoppingListBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function fixedPlan(User $user): MealPlan
{
    return MealPlan::factory()->for($user)->done()->create([
        'start_date' => '2026-11-16',
        'end_date' => '2026-11-22',
    ]);
}

function addMeal(MealPlan $plan, Recipe $recipe, string $date): void
{
    Meal::query()->create([
        'meal_plan_id' => $plan->id,
        'recipe_id' => $recipe->id,
        'meal_date' => $date,
        'meal_category' => $recipe->category,
    ]);
}

it('orders days by date and meals by category regardless of insertion order', function (): void {
    $user = User::factory()->create();
    $plan = fixedPlan($user);
    $dinner = Recipe::factory()->dinner()->for($user)->create(['name' => 'Kolacja A']);
    $breakfast = Recipe::factory()->breakfast()->for($user)->create(['name' => 'Sniadanie A']);
    $supper = Recipe::factory()->supper()->for($user)->create(['name' => 'Obiad A']);

    addMeal($plan, $dinner, '2026-11-17');
    addMeal($plan, $dinner, '2026-11-16');
    addMeal($plan, $supper, '2026-11-16');
    addMeal($plan, $breakfast, '2026-11-16');

    $document = app(MealPlanPdfExporter::class)->document($plan);

    expect($document->title)->toBe('Jadłospis 16.11.2026 – 22.11.2026')
        ->and(array_map(fn ($day) => $day->label, $document->days))->toBe(['Poniedziałek 16.11', 'Wtorek 17.11'])
        ->and(array_map(fn ($meal) => $meal->category, $document->days[0]->meals))->toBe(['Śniadanie', 'Obiad', 'Kolacja']);
});

it('lists the stored ingredient quantities in recipe order, without conversion', function (): void {
    $user = User::factory()->create();
    $plan = fixedPlan($user);
    $recipe = Recipe::factory()->breakfast()->for($user)->create();
    attachIngredient($recipe, 'Mąka', '100.00', 'g');
    attachIngredient($recipe, 'Mleko', '0.25', 'l');
    attachIngredient($recipe, 'Jajka', '2', 'szt');
    $big = Recipe::factory()->supper()->for($user)->create();
    attachIngredient($big, 'Ziemniaki', '1500', 'g');
    addMeal($plan, $recipe, '2026-11-16');
    addMeal($plan, $big, '2026-11-16');
    snapshotMealIngredients($plan);

    $meals = app(MealPlanPdfExporter::class)->document($plan)->days[0]->meals;

    expect($meals[0]->ingredients)->toBe(['Mąka — 100 g', 'Mleko — 0,25 l', 'Jajka — 2 szt.'])
        ->and($meals[1]->ingredients)->toBe(['Ziemniaki — 1500 g']);
});

it('handles recipes without ingredients and soft-deleted recipes', function (): void {
    $user = User::factory()->create();
    $plan = fixedPlan($user);
    $empty = Recipe::factory()->breakfast()->for($user)->create();
    $deleted = Recipe::factory()->supper()->for($user)->create();
    addMeal($plan, $empty, '2026-11-16');
    addMeal($plan, $deleted, '2026-11-16');
    $deleted->delete();

    $meals = app(MealPlanPdfExporter::class)->document($plan)->days[0]->meals;

    expect($meals[0]->ingredients)->toBe([])
        ->and($meals[0]->recipeDeleted)->toBeFalse()
        ->and($meals[1]->recipeDeleted)->toBeTrue();
});

it('skips meals without a recipe and omits days left empty', function (): void {
    $user = User::factory()->create();
    $plan = fixedPlan($user);
    $kept = Recipe::factory()->breakfast()->for($user)->create(['name' => 'Zostaje']);
    $lost = Recipe::factory()->supper()->for($user)->create(['name' => 'Znika']);
    addMeal($plan, $kept, '2026-11-16');
    addMeal($plan, $lost, '2026-11-16');
    addMeal($plan, $lost, '2026-11-17');

    $plan->load('meals.recipe');
    $plan->meals->where('recipe_id', $lost->id)->each(fn (Meal $meal) => $meal->setRelation('recipe', null));

    $days = app(MealPlanPdfExporter::class)->document($plan)->days;

    expect(array_map(fn ($day) => $day->label, $days))->toBe(['Poniedziałek 16.11'])
        ->and(array_map(fn ($meal) => $meal->recipeName, $days[0]->meals))->toBe(['Zostaje']);
});

it('lists the scaled snapshot, not the current recipe amounts, and skips zero quantities', function (): void {
    $user = User::factory()->create();
    $plan = fixedPlan($user);
    $recipe = Recipe::factory()->breakfast()->for($user)->create();
    attachIngredient($recipe, 'Mąka', '100', 'g');
    attachIngredient($recipe, 'Sól', '1', 'g');
    addMeal($plan, $recipe, '2026-11-16');
    snapshotMealIngredients($plan);
    $plan->load('meals.ingredients');
    $plan->meals->first()->ingredients->first()->update(['quantity' => '250']);
    $plan->meals->first()->ingredients->last()->update(['quantity' => '0']);
    $recipe->recipeIngredients()->update(['quantity' => '999']);

    $meals = app(MealPlanPdfExporter::class)->document($plan->fresh())->days[0]->meals;

    expect($meals[0]->ingredients)->toBe(['Mąka — 250 g']);
});

it('passes the shopping list through', function (): void {
    $user = User::factory()->create();
    $plan = fixedPlan($user);
    $recipe = Recipe::factory()->breakfast()->for($user)->create();
    attachIngredient($recipe, 'Mąka', '600', 'g');
    addMeal($plan, $recipe, '2026-11-16');
    snapshotMealIngredients($plan);

    $document = app(MealPlanPdfExporter::class)->document($plan);

    expect($document->shoppingList)->toEqual(app(ShoppingListBuilder::class)->forMealPlan($plan));
});

it('renders a PDF with the plan before the shopping list and Polish glyphs', function (): void {
    $user = User::factory()->create();
    $plan = fixedPlan($user);
    $salmon = Recipe::factory()->breakfast()->for($user)->create(['name' => 'Łosoś pieczony', 'instructions' => 'Piec w piekarniku.']);
    $soup = Recipe::factory()->supper()->for($user)->create(['name' => 'Żurek']);
    $supper = Recipe::factory()->dinner()->for($user)->create(['name' => 'Kolacja bez składników']);
    attachIngredient($salmon, 'Mąka', '600', 'g');
    attachIngredient($soup, 'Mąka', '600', 'g');
    addMeal($plan, $salmon, '2026-11-16');
    addMeal($plan, $soup, '2026-11-16');
    addMeal($plan, $supper, '2026-11-16');
    snapshotMealIngredients($plan);

    $bytes = app(MealPlanPdfExporter::class)->render($plan);
    $text = pdfText($bytes);

    expect($bytes)->toStartWith('%PDF-')
        ->and($text)->toContain('Poniedziałek 16.11', 'Śniadanie', 'Kolacja', 'Łosoś pieczony', 'Żurek', 'Mąka — 600 g', 'Piec w piekarniku', 'Bez składników')
        ->and(strpos($text, 'Łosoś pieczony'))->toBeLessThan(strpos($text, 'Lista zakupów'))
        ->and(strpos($text, 'Lista zakupów'))->toBeLessThan(strpos($text, '1,2 kg'));
});

it('prints the scaling notices under the title', function (): void {
    $user = User::factory()->create();
    $plan = fixedPlan($user);
    $plan->update(['generation_meta' => ['version' => 'scaled-v1', 'scaling' => ['status' => 'as_written']]]);
    $recipe = Recipe::factory()->breakfast()->for($user)->create(['name' => 'Naleśniki']);
    attachIngredient($recipe, 'Mąka', '600', 'g');
    addMeal($plan, $recipe, '2026-11-16');
    snapshotMealIngredients($plan);

    $text = pdfText(app(MealPlanPdfExporter::class)->render($plan));

    expect($text)->toContain('Dodaj członków rodziny, aby przeliczyć składniki. Ilości są podane jak w przepisach.', 'Mąka — 600 g')
        ->and(strpos($text, 'Jadłospis 16.11.2026'))->toBeLessThan(strpos($text, 'Dodaj członków rodziny'))
        ->and(strpos($text, 'Dodaj członków rodziny'))->toBeLessThan(strpos($text, 'Naleśniki'));
});

it('prints the amounts scaled to the family together with their notice', function (): void {
    $user = User::factory()->create();
    seedTwoAdultFamily($user);
    // Without body data the man falls back to the 75 kg reference row: still 3000 kcal, but named in the notice.
    $user->familyMembers()->where('first_name', 'Adam')->update(['weight_kg' => null, 'height_cm' => null]);
    $plan = fixedPlan($user);
    $recipe = Recipe::factory()->breakfast()->for($user)->create(['name' => 'Naleśniki', 'calories' => 810]);
    attachIngredient($recipe, 'Mąka', '100', 'g');
    addMeal($plan, $recipe, '2026-11-16');
    $outcome = app(MealIngredientScaler::class)->scale($plan);
    $plan->update(['generation_meta' => ['version' => 'scaled-v1', 'scaling' => $outcome->toArray()]]);

    $text = pdfText(app(MealPlanPdfExporter::class)->render($plan->fresh()));

    expect($text)->toContain('Uzupełnij wagę i wzrost, aby dokładniej przeliczyć porcje: Adam.', 'Mąka — 200 g')
        ->not->toContain('Mąka — 100 g');
});

it('marks soft-deleted recipes in the PDF', function (): void {
    $user = User::factory()->create();
    $plan = fixedPlan($user);
    $recipe = Recipe::factory()->breakfast()->for($user)->create();
    addMeal($plan, $recipe, '2026-11-16');
    $recipe->delete();

    expect(pdfText(app(MealPlanPdfExporter::class)->render($plan)))->toContain('(usunięty)');
});

it('builds the filename from the plan dates', function (): void {
    $plan = fixedPlan(User::factory()->create());

    expect(app(MealPlanPdfExporter::class)->filename($plan))->toBe('jadlospis-2026-11-16-2026-11-22.pdf');
});
