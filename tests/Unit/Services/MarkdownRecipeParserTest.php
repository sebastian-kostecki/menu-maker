<?php

declare(strict_types=1);

use App\Services\RecipeImport\MarkdownRecipeParser;
use App\Services\RecipeImport\ParsedIngredient;
use App\Services\RecipeImport\ParsedRecipe;

function parseRecipe(string $markdown, ?string $defaultCalories = null, ?int $defaultServings = null): ParsedRecipe
{
    return (new MarkdownRecipeParser)->parse('Gofry  ', $markdown, $defaultCalories, $defaultServings);
}

/**
 * @return array<string, array{0: string, 1: string}>
 */
function ingredientMap(ParsedRecipe $recipe): array
{
    return collect($recipe->ingredients)
        ->mapWithKeys(fn (ParsedIngredient $ingredient): array => [$ingredient->name => [$ingredient->quantity, $ingredient->unitCode]])
        ->all();
}

it('parses the front matter, the ingredient table and the steps', function (): void {
    $recipe = parseRecipe(<<<'MD'
        ---
        kategoria: śniadanie
        Typ: placki
        źródło: "Segregator: str. 7"
        kalorie: 1450
        porcje: 4
        ---
        # Składniki
        | Składnki             | Ilość        |
        | -------------------- | ------------ |
        | masło                | 85 g         |
        | cebula               | 160g         |
        | jajka                | 2            |
        | proszek do pieczenia | 0,5 łyżeczki |
        | majeranek            | 1 łyżka      |
        | sól                  | 1 szczypta   |
        | pieprz               | szczypta     |
        | mleko                | 1/2 szklanki |
        # Sposób przygotowania
        - wymieszaj składniki
        - smaż na patelni
        MD);

    expect($recipe->isImportable())->toBeTrue()
        ->and($recipe->name)->toBe('Gofry')
        ->and($recipe->category)->toBe('breakfast')
        ->and($recipe->calories)->toBe('1450')
        ->and($recipe->servings)->toBe(4)
        ->and($recipe->instructions)->toBe("wymieszaj składniki\nsmaż na patelni\n\nŹródło: Segregator: str. 7")
        ->and($recipe->warnings)->toBe([])
        ->and(ingredientMap($recipe))->toBe([
            'Masło' => ['85.00', 'g'],
            'Cebula' => ['160.00', 'g'],
            'Jajka' => ['2.00', 'szt'],
            'Proszek do pieczenia' => ['2.50', 'ml'],
            'Majeranek' => ['15.00', 'ml'],
            'Sól' => ['0.50', 'g'],
            'Pieprz' => ['0.50', 'g'],
            'Mleko' => ['125.00', 'ml'],
        ]);
});

it('maps the Polish category to the stored code', function (string $polish, string $code): void {
    $recipe = parseRecipe("---\nkategoria: {$polish}\n---\n# Składniki\n| a | b |\n| - | - |\n| ryż | 100 g |\n# Sposób przygotowania\n- gotuj", '500', 2);

    expect($recipe->category)->toBe($code);
})->with([
    ['śniadanie', 'breakfast'],
    ['Obiad', 'supper'],
    ['kolacja', 'dinner'],
]);

it('rejects a file without category, ingredients, steps, calories and servings', function (): void {
    $recipe = parseRecipe("---\nkategoria:\nTyp: placki\nźródło:\n---\n");

    expect($recipe->isImportable())->toBeFalse()
        ->and($recipe->errors)->toHaveCount(5)
        ->and(implode(' ', $recipe->errors))->toContain('brak kategorii', 'brak składników', 'brak sposobu przygotowania', 'brak kaloryczności', 'brak liczby porcji');
});

it('rejects an unknown category and calories below 50', function (): void {
    $recipe = parseRecipe("---\nkategoria: deser\nkalorie: 40\nporcje: 2\n---\n# Składniki\n| a | b |\n| - | - |\n| ryż | 100 g |\n# Sposób przygotowania\n- gotuj");

    expect($recipe->errors)->toHaveCount(2)
        ->and($recipe->errors[0])->toContain('nieznana kategoria „deser”')
        ->and($recipe->errors[1])->toContain('co najmniej 50 kcal');
});

it('falls back to the default calories and servings with a warning', function (): void {
    $recipe = parseRecipe("---\nkategoria: obiad\n---\n# Składniki\n| a | b |\n| - | - |\n| ryż | 100 g |\n# Sposób przygotowania\n- gotuj", '650', 3);

    expect($recipe->isImportable())->toBeTrue()
        ->and($recipe->calories)->toBe('650')
        ->and($recipe->servings)->toBe(3)
        ->and($recipe->warnings)->toBe(['użyto domyślnej kaloryczności 650 kcal', 'użyto domyślnej liczby porcji 3']);
});

it('skips rows without a quantity or with an unknown unit and ignores empty rows', function (): void {
    $recipe = parseRecipe(<<<'MD'
        ---
        kategoria: obiad
        kalorie: 900
        porcje: 2
        ---
        # Składniki
        | Składniki | Ilość |
        | --------- | ----- |
        | brokuły   | 480 g |
        | oregano   |       |
        | koperek   | 1 pęczek |
        |           |       |
        # Sposób przygotowania
        - gotuj
        MD);

    expect($recipe->isImportable())->toBeTrue()
        ->and(ingredientMap($recipe))->toBe(['Brokuły' => ['480.00', 'g']])
        ->and($recipe->warnings)->toBe([
            'pominięto składnik „Oregano”: brak ilości',
            'pominięto składnik „Koperek”: nieznana jednostka „pęczek”',
        ]);
});

it('sums a repeated ingredient in the same unit and keeps the first row otherwise', function (): void {
    $recipe = parseRecipe(<<<'MD'
        ---
        kategoria: śniadanie
        kalorie: 900
        porcje: 2
        ---
        # Składniki
        | Składniki | Ilość |
        | --------- | ----- |
        | olej      | 30 g  |
        | Olej      | 30 g  |
        | sól       | 2 g   |
        | sól       | 1 łyżeczka |
        # Sposób przygotowania
        - smaż
        MD);

    expect(ingredientMap($recipe))->toBe(['Olej' => ['60.00', 'g'], 'Sól' => ['2.00', 'g']])
        ->and($recipe->warnings)->toHaveCount(2);
});

it('warns when a large quantity has no unit', function (): void {
    $recipe = parseRecipe("---\nkategoria: kolacja\nkalorie: 900\nporcje: 2\n---\n# Składniki\n| a | b |\n| - | - |\n| mozzarella | 125 |\n| jajka | 2 |\n# Sposób przygotowania\n- piecz");

    expect(ingredientMap($recipe))->toBe(['Mozzarella' => ['125.00', 'szt'], 'Jajka' => ['2.00', 'szt']])
        ->and($recipe->warnings)->toBe(['składnik „Mozzarella”: „125” bez jednostki potraktowano jako sztuki (może brakować „g”?)']);
});

it('warns when the steps only point to the source', function (): void {
    $recipe = parseRecipe("---\nkategoria: obiad\nźródło: Lidlomix\nkalorie: 900\nporcje: 2\n---\n# Składniki\n| a | b |\n| - | - |\n| ryż | 100 g |\n# Sposób przygotowania\n- Według Lidlomixa");

    expect($recipe->isImportable())->toBeTrue()
        ->and($recipe->instructions)->toBe("Według Lidlomixa\n\nŹródło: Lidlomix")
        ->and($recipe->warnings)->toBe(['sposób przygotowania to tylko odnośnik („Według Lidlomixa”)']);
});
