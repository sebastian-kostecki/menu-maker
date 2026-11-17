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

function addEmptyMeal(MealPlan $plan, string $date, string $category): void
{
    Meal::query()->create([
        'meal_plan_id' => $plan->id,
        'recipe_id' => null,
        'meal_date' => $date,
        'meal_category' => $category,
    ]);
}

it('keeps a meal without a recipe as an empty row and adds no recipe page', function (): void {
    $user = User::factory()->create();
    $plan = fixedPlan($user);
    $kept = Recipe::factory()->breakfast()->for($user)->create(['name' => 'Zostaje']);
    addMeal($plan, $kept, '2026-11-16');
    addEmptyMeal($plan, '2026-11-16', 'supper');

    $document = app(MealPlanPdfExporter::class)->document($plan, true);
    $meals = $document->days[0]->meals;

    expect($document->days)->toHaveCount(1)
        ->and($meals)->toHaveCount(2)
        ->and($meals[0]->empty)->toBeFalse()
        ->and($meals[1]->empty)->toBeTrue()
        ->and($meals[1]->recipeName)->toBe('Bez posiłku')
        ->and($meals[1]->recipeDeleted)->toBeFalse()
        ->and($meals[1]->ingredients)->toBe([])
        ->and($document->recipes)->toHaveCount(1);
});

it('keeps a day whose meals are all empty', function (): void {
    $user = User::factory()->create();
    $plan = fixedPlan($user);
    addEmptyMeal($plan, '2026-11-17', 'breakfast');
    addEmptyMeal($plan, '2026-11-17', 'supper');
    addEmptyMeal($plan, '2026-11-17', 'dinner');

    $days = app(MealPlanPdfExporter::class)->document($plan, true)->days;

    expect($days)->toHaveCount(1)
        ->and($days[0]->label)->toBe('Wtorek 17.11')
        ->and($days[0]->meals)->toHaveCount(3)
        ->and(array_map(fn ($meal) => $meal->empty, $days[0]->meals))->toBe([true, true, true]);
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

/**
 * Monday's Obiad (cooked) feeds Tuesday's Obiad (carried over); the recipe has Mąka 100 g and the owner has no family.
 */
function linkedSupperPlan(User $user): MealPlan
{
    $plan = fixedPlan($user);
    $recipe = Recipe::factory()->supper()->for($user)->create(['name' => 'Gulasz']);
    attachIngredient($recipe, 'Mąka', '100', 'g');
    addMeal($plan, $recipe, '2026-11-16');
    addMeal($plan, $recipe, '2026-11-17');
    $cooked = Meal::query()->where('meal_plan_id', $plan->id)->where('meal_date', '2026-11-16')->firstOrFail();
    $carriedOver = Meal::query()->where('meal_plan_id', $plan->id)->where('meal_date', '2026-11-17')->firstOrFail();
    $cooked->update(['feeds_meal_id' => $carriedOver->id]);

    return snapshotMealIngredients($plan);
}

it('a carried-over meal prints Z wczoraj with no ingredients', function (): void {
    $plan = linkedSupperPlan(User::factory()->create());

    $meal = app(MealPlanPdfExporter::class)->document($plan->fresh())->days[1]->meals[0];

    expect($meal->recipeName)->toBe('Gulasz')
        ->and($meal->marker)->toBe('Z wczoraj')
        ->and($meal->ingredients)->toBe([])
        ->and($meal->carriedOver)->toBeTrue()
        ->and($meal->empty)->toBeFalse();
});

it("leaves a carried-over meal's ingredients cell empty", function (): void {
    $plan = linkedSupperPlan(User::factory()->create());

    $html = view('pdf.meal-plan', ['document' => app(MealPlanPdfExporter::class)->document($plan->fresh())])->render();

    expect($html)->toMatch('#<span class="meta">Z wczoraj</span></td>\s*<td></td>#');
});

it('a cooked meal prints Na 2 dni with its doubled lines', function (): void {
    $plan = linkedSupperPlan(User::factory()->create());

    $meal = app(MealPlanPdfExporter::class)->document($plan->fresh())->days[0]->meals[0];

    expect($meal->marker)->toBe('Na 2 dni')
        ->and($meal->ingredients)->toBe(['Mąka — 200 g']);
});

it('a linked pair is one use on the recipe page labelled na 2 dni', function (): void {
    $plan = linkedSupperPlan(User::factory()->create());

    $recipes = app(MealPlanPdfExporter::class)->document($plan->fresh(), true)->recipes;

    expect($recipes)->toHaveCount(1)
        ->and($recipes[0]->ingredientGroups)->toHaveCount(1)
        ->and($recipes[0]->ingredientGroups[0]->dayLabels)->toBe(['Poniedziałek 16.11 (na 2 dni)'])
        ->and($recipes[0]->ingredientGroups[0]->lines)->toBe(['Mąka — 200 g']);
});

/**
 * Monday's Kolacja (cooked) feeds Tuesday's Śniadanie (carried over); the recipe has Mąka 100 g and the owner has no family.
 */
function linkedKolacjaPlan(User $user): MealPlan
{
    $plan = fixedPlan($user);
    $recipe = Recipe::factory()->dinner()->for($user)->create(['name' => 'Leczo']);
    attachIngredient($recipe, 'Mąka', '100', 'g');
    addMeal($plan, $recipe, '2026-11-16');
    $cooked = Meal::query()->where('meal_plan_id', $plan->id)->where('meal_date', '2026-11-16')->firstOrFail();
    $carriedOver = Meal::query()->create([
        'meal_plan_id' => $plan->id,
        'recipe_id' => $recipe->id,
        'meal_date' => '2026-11-17',
        'meal_category' => 'breakfast',
    ]);
    $cooked->update(['feeds_meal_id' => $carriedOver->id]);

    return snapshotMealIngredients($plan);
}

it('a Śniadanie carried over from Kolacja prints Z kolacji in the breakfast category with an empty ingredients cell', function (): void {
    $plan = linkedKolacjaPlan(User::factory()->create());
    $document = app(MealPlanPdfExporter::class)->document($plan->fresh());
    $meal = $document->days[1]->meals[0];

    $html = view('pdf.meal-plan', ['document' => $document])->render();

    expect($meal->recipeName)->toBe('Leczo')
        ->and($meal->categoryValue)->toBe('breakfast')
        ->and($meal->marker)->toBe('Z kolacji')
        ->and($meal->ingredients)->toBe([])
        ->and($meal->carriedOver)->toBeTrue()
        ->and($html)->toMatch('#<span class="meta">Z kolacji</span></td>\s*<td></td>#');
});

it('a Kolacja feeding breakfast prints Też na śniadanie with its doubled lines', function (): void {
    $plan = linkedKolacjaPlan(User::factory()->create());

    $meal = app(MealPlanPdfExporter::class)->document($plan->fresh())->days[0]->meals[0];

    expect($meal->marker)->toBe('Też na śniadanie')
        ->and($meal->ingredients)->toBe(['Mąka — 200 g']);
});

it('a Kolacja carried to breakfast is one use on the recipe page labelled też na śniadanie', function (): void {
    $plan = linkedKolacjaPlan(User::factory()->create());

    $recipes = app(MealPlanPdfExporter::class)->document($plan->fresh(), true)->recipes;

    expect($recipes)->toHaveCount(1)
        ->and($recipes[0]->ingredientGroups)->toHaveCount(1)
        ->and($recipes[0]->ingredientGroups[0]->dayLabels)->toBe(['Poniedziałek 16.11 (też na śniadanie)'])
        ->and($recipes[0]->ingredientGroups[0]->lines)->toBe(['Mąka — 200 g']);
});
