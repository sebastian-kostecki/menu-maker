<?php

declare(strict_types=1);

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

const VALID_RECIPE = <<<'MD'
    ---
    kategoria: obiad
    źródło: Własne
    kalorie: 1200
    porcje: 4
    ---
    # Składniki
    | Składniki | Ilość      |
    | --------- | ---------- |
    | ryż       | 300 g      |
    | marchew   | 3          |
    | sól       | 1 łyżeczka |
    # Sposób przygotowania
    - podsmaż warzywa
    - dodaj ryż
    MD;

beforeEach(function (): void {
    $this->user = User::factory()->create(['email' => 'owner@example.com']);
    $this->directory = sys_get_temp_dir().'/recipes-import-'.uniqid();
    File::makeDirectory($this->directory);
    File::put($this->directory.'/Plow.md', VALID_RECIPE);
    File::put($this->directory.'/Pancakes z tofu.md', "---\nkategoria: śniadanie\n---\n");
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

it('imports valid files for the owner and reports the invalid ones', function (): void {
    $this->artisan('recipes:import', ['path' => $this->directory, '--user' => 'owner@example.com'])
        ->expectsOutputToContain('Zaimportowano: 1, pominięto: 0, błędy: 1.')
        ->assertFailed();

    $recipe = Recipe::query()->forUser($this->user->id)->with('recipeIngredients.ingredient', 'recipeIngredients.unit')->sole();

    expect($recipe->name)->toBe('Plow')
        ->and($recipe->category)->toBe('supper')
        ->and((string) $recipe->calories)->toBe('1200.00')
        ->and($recipe->servings)->toBe(4)
        ->and($recipe->instructions)->toBe("podsmaż warzywa\ndodaj ryż\n\nŹródło: Własne")
        ->and($recipe->recipeIngredients->mapWithKeys(fn ($row): array => [
            $row->ingredient->name => [(string) $row->quantity, $row->unit->code],
        ])->all())->toEqualCanonicalizing([
            'Ryż' => ['300.00', 'g'],
            'Marchew' => ['3.00', 'szt'],
            'Sól' => ['5.00', 'ml'],
        ]);
});

it('writes nothing on a dry run', function (): void {
    $this->artisan('recipes:import', ['path' => $this->directory, '--user' => (string) $this->user->id, '--dry-run' => true])
        ->expectsOutputToContain('Do importu: 1, pominięto: 0, błędy: 1. Nic nie zapisano (--dry-run).');

    expect(Recipe::query()->count())->toBe(0);
});

it('skips a name the owner already has but not one owned by someone else', function (): void {
    Recipe::factory()->for($this->user)->create(['name' => 'PLOW']);
    File::put($this->directory.'/Gulasz.md', str_replace('Własne', 'Segregator', VALID_RECIPE));
    Recipe::factory()->for(User::factory())->create(['name' => 'Gulasz']);
    File::delete($this->directory.'/Pancakes z tofu.md');

    $this->artisan('recipes:import', ['path' => $this->directory, '--user' => 'owner@example.com'])
        ->expectsOutputToContain('Zaimportowano: 1, pominięto: 1, błędy: 0.')
        ->assertSuccessful();

    expect(Recipe::query()->forUser($this->user->id)->pluck('name')->sort()->values()->all())->toBe(['Gulasz', 'PLOW']);
});

it('uses the default calories and servings for files without them', function (): void {
    File::put($this->directory.'/Plow.md', str_replace(["kalorie: 1200\n", "porcje: 4\n"], '', VALID_RECIPE));

    $this->artisan('recipes:import', ['path' => $this->directory, '--user' => 'owner@example.com', '--calories' => '800', '--servings' => '2'])
        ->expectsOutputToContain('Zaimportowano: 1');

    $recipe = Recipe::query()->forUser($this->user->id)->sole();

    expect((string) $recipe->calories)->toBe('800.00')
        ->and($recipe->servings)->toBe(2);
});

it('fails without a known owner, an existing directory or valid defaults', function (array $arguments, string $message): void {
    $this->artisan('recipes:import', ['path' => $this->directory, ...$arguments])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(Recipe::query()->count())->toBe(0);
})->with([
    'no user' => [[], 'Podaj właściciela przepisów'],
    'unknown user' => [['--user' => 'nobody@example.com'], 'Nie znaleziono użytkownika „nobody@example.com”.'],
    'calories below 50' => [['--user' => 'owner@example.com', '--calories' => '20'], '--calories musi być liczbą co najmniej 50'],
    'zero servings' => [['--user' => 'owner@example.com', '--servings' => '0'], '--servings musi być dodatnią liczbą całkowitą.'],
]);

it('fails for a missing directory', function (): void {
    $this->artisan('recipes:import', ['path' => $this->directory.'/missing', '--user' => 'owner@example.com'])
        ->expectsOutputToContain('nie istnieje')
        ->assertFailed();
});
