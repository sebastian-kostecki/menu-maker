<?php

declare(strict_types=1);

use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
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

it('lists ingredients as written, in recipe order, without conversion', function (): void {
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

it('passes the shopping list through', function (): void {
    $user = User::factory()->create();
    $plan = fixedPlan($user);
    $recipe = Recipe::factory()->breakfast()->for($user)->create();
    attachIngredient($recipe, 'Mąka', '600', 'g');
    addMeal($plan, $recipe, '2026-11-16');

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

    $bytes = app(MealPlanPdfExporter::class)->render($plan);
    $text = pdfText($bytes);

    expect($bytes)->toStartWith('%PDF-')
        ->and($text)->toContain('Poniedziałek 16.11', 'Śniadanie', 'Kolacja', 'Łosoś pieczony', 'Żurek', 'Mąka — 600 g', 'Piec w piekarniku', 'Bez składników')
        ->and(strpos($text, 'Łosoś pieczony'))->toBeLessThan(strpos($text, 'Lista zakupów'))
        ->and(strpos($text, 'Lista zakupów'))->toBeLessThan(strpos($text, '1,2 kg'));
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
