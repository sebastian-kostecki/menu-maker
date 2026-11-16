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
use Smalot\PdfParser\Parser;

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
        ->and(array_map(fn ($meal) => $meal->category, $document->days[0]->meals))->toBe(['Śniadanie', 'Obiad', 'Kolacja'])
        ->and(array_map(fn ($meal) => $meal->categoryValue, $document->days[0]->meals))->toBe(['breakfast', 'supper', 'dinner']);
});

it('colours each category cell with its meal class and frames the scaling notices', function (): void {
    $user = User::factory()->create();
    $plan = fixedPlan($user);
    $plan->update(['generation_meta' => ['version' => 'scaled-v1', 'scaling' => ['status' => 'as_written']]]);
    addMeal($plan, Recipe::factory()->dinner()->for($user)->create(), '2026-11-16');
    addMeal($plan, Recipe::factory()->breakfast()->for($user)->create(), '2026-11-16');
    addMeal($plan, Recipe::factory()->supper()->for($user)->create(), '2026-11-16');

    $html = view('pdf.meal-plan', ['document' => app(MealPlanPdfExporter::class)->document($plan)])->render();

    expect($html)->toContain(
        'class="category meal-breakfast">Śniadanie<',
        'class="category meal-supper">Obiad<',
        'class="category meal-dinner">Kolacja<',
        '<div class="notices">',
    );
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
        ->and($text)->toContain('Poniedziałek 16.11', 'Śniadanie', 'Kolacja', 'Łosoś pieczony', 'Żurek', 'Mąka — 600 g', 'Bez składników')
        ->not->toContain('Piec w piekarniku', 'Przygotowanie')
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

    expect(app(MealPlanPdfExporter::class)->filename($plan))->toBe('jadlospis-2026-11-16-2026-11-22.pdf')
        ->and(app(MealPlanPdfExporter::class)->filename($plan, true))->toBe('jadlospis-2026-11-16-2026-11-22-z-przepisami.pdf');
});

it('has no recipe pages unless asked for', function (): void {
    $user = User::factory()->create();
    $plan = fixedPlan($user);
    addMeal($plan, Recipe::factory()->breakfast()->for($user)->create(), '2026-11-16');

    expect(app(MealPlanPdfExporter::class)->document($plan)->recipes)->toBe([]);
});

it('orders recipe pages by first use in the week, not by insertion', function (): void {
    $user = User::factory()->create();
    $plan = fixedPlan($user);
    $tuesday = Recipe::factory()->breakfast()->for($user)->create(['name' => 'Wtorkowe']);
    $mondayDinner = Recipe::factory()->dinner()->for($user)->create(['name' => 'Poniedzialkowa kolacja']);
    $mondayBreakfast = Recipe::factory()->breakfast()->for($user)->create(['name' => 'Poniedzialkowe']);

    addMeal($plan, $tuesday, '2026-11-17');
    addMeal($plan, $mondayDinner, '2026-11-16');
    addMeal($plan, $mondayBreakfast, '2026-11-16');

    $recipes = app(MealPlanPdfExporter::class)->document($plan, true)->recipes;

    expect(array_map(fn ($recipe) => $recipe->name, $recipes))->toBe(['Poniedzialkowe', 'Poniedzialkowa kolacja', 'Wtorkowe']);
});

it('groups the uses of a recipe by their printed amounts', function (): void {
    $user = User::factory()->create();
    $plan = fixedPlan($user);
    $recipe = Recipe::factory()->breakfast()->for($user)->create();
    attachIngredient($recipe, 'Mąka', '100', 'g');
    foreach (['2026-11-16', '2026-11-18', '2026-11-20'] as $date) {
        addMeal($plan, $recipe, $date);
    }
    snapshotMealIngredients($plan);
    $plan->load('meals.ingredients');
    $friday = $plan->meals->first(fn (Meal $meal) => $meal->meal_date->format('Y-m-d') === '2026-11-20');
    $friday->ingredients->first()->update(['quantity' => '250']);

    $recipes = app(MealPlanPdfExporter::class)->document($plan->fresh(), true)->recipes;

    expect($recipes)->toHaveCount(1)
        ->and($recipes[0]->ingredientGroups)->toHaveCount(2)
        ->and($recipes[0]->ingredientGroups[0]->dayLabels)->toBe(['Poniedziałek 16.11', 'Środa 18.11'])
        ->and($recipes[0]->ingredientGroups[0]->lines)->toBe(['Mąka — 100 g'])
        ->and($recipes[0]->ingredientGroups[1]->dayLabels)->toBe(['Piątek 20.11'])
        ->and($recipes[0]->ingredientGroups[1]->lines)->toBe(['Mąka — 250 g']);
});

it('gives a use without printable lines its own group', function (): void {
    $user = User::factory()->create();
    $plan = fixedPlan($user);
    $recipe = Recipe::factory()->breakfast()->for($user)->create();
    attachIngredient($recipe, 'Mąka', '100', 'g');
    addMeal($plan, $recipe, '2026-11-16');
    addMeal($plan, $recipe, '2026-11-17');
    snapshotMealIngredients($plan);
    $plan->load('meals.ingredients');
    $tuesday = $plan->meals->first(fn (Meal $meal) => $meal->meal_date->format('Y-m-d') === '2026-11-17');
    $tuesday->ingredients->each(fn ($ingredient) => $ingredient->update(['quantity' => '0']));

    $groups = app(MealPlanPdfExporter::class)->document($plan->fresh(), true)->recipes[0]->ingredientGroups;

    expect($groups)->toHaveCount(2)
        ->and($groups[0]->lines)->toBe(['Mąka — 100 g'])
        ->and($groups[1]->dayLabels)->toBe(['Wtorek 17.11'])
        ->and($groups[1]->lines)->toBe([]);
});

it('takes the recipe page fields from the recipe', function (): void {
    $user = User::factory()->create();
    $plan = fixedPlan($user);
    $recipe = Recipe::factory()->supper()->for($user)->create(['name' => 'Rosół', 'instructions' => "Krok 1\nKrok 2"]);
    Meal::query()->create([
        'meal_plan_id' => $plan->id,
        'recipe_id' => $recipe->id,
        'meal_date' => '2026-11-16',
        'meal_category' => 'breakfast',
    ]);
    $recipe->delete();

    $page = app(MealPlanPdfExporter::class)->document($plan, true)->recipes[0];

    expect($page->name)->toBe('Rosół')
        ->and($page->category)->toBe('Obiad')
        ->and($page->categoryValue)->toBe('supper')
        ->and($page->deleted)->toBeTrue()
        ->and($page->instructions)->toBe("Krok 1\nKrok 2");
});

it('appends one page per recipe after the shopping list', function (): void {
    $user = User::factory()->create();
    $plan = fixedPlan($user);
    $first = Recipe::factory()->breakfast()->for($user)->create(['name' => 'Jajecznica', 'instructions' => 'Smażyć na patelni.']);
    $second = Recipe::factory()->supper()->for($user)->create(['name' => 'Rosół', 'instructions' => 'Gotować długo.']);
    attachIngredient($first, 'Jajka', '4', 'szt');
    addMeal($plan, $first, '2026-11-16');
    addMeal($plan, $first, '2026-11-17');
    addMeal($plan, $second, '2026-11-16');
    snapshotMealIngredients($plan);
    $exporter = app(MealPlanPdfExporter::class);

    $without = (new Parser)->parseContent($exporter->render($plan));
    $withBytes = $exporter->render($plan, true);
    $with = (new Parser)->parseContent($withBytes);
    $text = pdfText($withBytes);

    expect(count($with->getPages()))->toBe(count($without->getPages()) + 2)
        ->and($text)->toContain('Smażyć na patelni.', 'Gotować długo.', 'Przygotowanie', 'Poniedziałek 16.11 · Wtorek 17.11')
        ->and(strpos($text, 'Smażyć na patelni.'))->toBeGreaterThan(strpos($text, 'Lista zakupów'));
});

it('marks deleted recipes and recipes without ingredients on their page', function (): void {
    $user = User::factory()->create();
    $plan = fixedPlan($user);
    $recipe = Recipe::factory()->breakfast()->for($user)->create(['name' => 'Stara zupa']);
    addMeal($plan, $recipe, '2026-11-16');
    $recipe->delete();

    $text = pdfText(app(MealPlanPdfExporter::class)->render($plan, true));

    expect(substr_count($text, '(usunięty)'))->toBe(2)
        ->and(substr_count($text, 'Bez składników'))->toBe(2);
});
