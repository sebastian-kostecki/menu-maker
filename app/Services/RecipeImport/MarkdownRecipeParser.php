<?php

declare(strict_types=1);

namespace App\Services\RecipeImport;

use App\Models\Ingredient;

/**
 * Parses a recipe written as Markdown: a YAML-like front matter (kategoria, źródło, kalorie, porcje),
 * a "Składniki" table (name | quantity) and a "Sposób przygotowania" list.
 */
final class MarkdownRecipeParser
{
    /**
     * Polish category from the file to the stored category code.
     *
     * @var array<string, 'breakfast'|'supper'|'dinner'>
     */
    public const CATEGORY_MAP = [
        'śniadanie' => 'breakfast',
        'obiad' => 'supper',
        'kolacja' => 'dinner',
    ];

    /**
     * Unit word from the file to [allowed unit code, multiplier]. Spoons and glasses become ml,
     * as the 2026_11_13 unit migration did (tsp 5 ml, tbsp 15 ml, cup 250 ml); a pinch becomes 0.5 g.
     *
     * @var array<string, array{0: string, 1: numeric-string}>
     */
    private const UNIT_ALIASES = [
        'g' => ['g', '1'],
        'gram' => ['g', '1'],
        'gramy' => ['g', '1'],
        'gramów' => ['g', '1'],
        'dag' => ['g', '10'],
        'kg' => ['kg', '1'],
        'ml' => ['ml', '1'],
        'l' => ['l', '1'],
        'szt' => ['szt', '1'],
        'sztuka' => ['szt', '1'],
        'sztuki' => ['szt', '1'],
        'sztuk' => ['szt', '1'],
        'łyżeczka' => ['ml', '5'],
        'łyżeczki' => ['ml', '5'],
        'łyżeczek' => ['ml', '5'],
        'łyżka' => ['ml', '15'],
        'łyżki' => ['ml', '15'],
        'łyżek' => ['ml', '15'],
        'szklanka' => ['ml', '250'],
        'szklanki' => ['ml', '250'],
        'szklanek' => ['ml', '250'],
        'szczypta' => ['g', '0.5'],
        'szczypty' => ['g', '0.5'],
        'szczypt' => ['g', '0.5'],
    ];

    private const SCALE = 6;

    /**
     * A bare number at or above this is more likely grams with a missing unit than a piece count.
     */
    private const SUSPICIOUS_PIECES = '20';

    /**
     * @param  string  $name  Recipe name, usually the file name without the extension.
     * @param  numeric-string|null  $defaultCalories  Used when the front matter has no "kalorie".
     * @param  int|null  $defaultServings  Used when the front matter has no "porcje".
     */
    public function parse(string $name, string $markdown, ?string $defaultCalories = null, ?int $defaultServings = null): ParsedRecipe
    {
        $warnings = [];
        $errors = [];

        [$frontMatter, $body] = $this->splitFrontMatter($markdown);
        $sections = $this->sections($body);

        $category = $this->category($frontMatter['kategoria'] ?? null, $errors);
        $ingredients = $this->ingredients($sections['ingredients'] ?? [], $warnings);
        $instructions = $this->instructions($sections['instructions'] ?? [], $frontMatter['źródło'] ?? '', $warnings, $errors);
        $calories = $this->calories($frontMatter['kalorie'] ?? $frontMatter['kcal'] ?? null, $defaultCalories, $warnings, $errors);
        $servings = $this->servings($frontMatter['porcje'] ?? null, $defaultServings, $warnings, $errors);

        if ($ingredients === []) {
            $errors[] = 'brak składników';
        }

        return new ParsedRecipe(
            name: Ingredient::normalizeName($name),
            category: $category,
            instructions: $instructions,
            calories: $calories,
            servings: $servings,
            ingredients: $ingredients,
            warnings: $warnings,
            errors: $errors,
        );
    }

    /**
     * @return array{0: array<string, string>, 1: string}
     */
    private function splitFrontMatter(string $markdown): array
    {
        $markdown = (string) preg_replace('/^\x{FEFF}/u', '', $markdown);

        if (preg_match('/\A---\R(.*?)\R---[ \t]*(?:\R|\z)(.*)\z/su', $markdown, $matches) !== 1) {
            return [[], $markdown];
        }

        $frontMatter = [];

        foreach ((array) preg_split('/\R/u', $matches[1]) as $line) {
            $parts = explode(':', (string) $line, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $key = mb_strtolower(trim($parts[0]));
            $value = trim($parts[1]);

            if (preg_match('/^([\'"])(.*)\1$/u', $value, $quoted) === 1) {
                $value = trim($quoted[2]);
            }

            $frontMatter[$key] = $value;
        }

        return [$frontMatter, $matches[2]];
    }

    /**
     * Lines grouped under the "Składniki" and "Sposób przygotowania" headings; other headings are ignored.
     *
     * @return array<'ingredients'|'instructions', list<string>>
     */
    private function sections(string $body): array
    {
        $sections = [];
        $current = null;

        foreach ((array) preg_split('/\R/u', $body) as $line) {
            $line = (string) $line;

            if (preg_match('/^#{1,6}\s+(.+)$/u', $line, $heading) === 1) {
                $current = $this->sectionKey($heading[1]);

                continue;
            }

            if ($current !== null) {
                $sections[$current][] = $line;
            }
        }

        return $sections;
    }

    /**
     * @return 'ingredients'|'instructions'|null
     */
    private function sectionKey(string $heading): ?string
    {
        $heading = mb_strtolower($heading);

        if (str_contains($heading, 'składnik')) {
            return 'ingredients';
        }

        if (str_contains($heading, 'przygotowa') || str_contains($heading, 'sposób') || str_contains($heading, 'instrukcj')) {
            return 'instructions';
        }

        return null;
    }

    /**
     * @param  list<string>  $errors
     * @return 'breakfast'|'supper'|'dinner'|null
     */
    private function category(?string $value, array &$errors): ?string
    {
        $value = mb_strtolower(trim((string) $value));

        if ($value === '') {
            $errors[] = 'brak kategorii („kategoria:” w nagłówku)';

            return null;
        }

        if (! isset(self::CATEGORY_MAP[$value])) {
            $errors[] = "nieznana kategoria „{$value}” (dozwolone: ".implode(', ', array_keys(self::CATEGORY_MAP)).')';

            return null;
        }

        return self::CATEGORY_MAP[$value];
    }

    /**
     * Reads the table rows (the first row is the header), converts units and merges repeated ingredients.
     *
     * @param  list<string>  $lines
     * @param  list<string>  $warnings
     * @return list<ParsedIngredient>
     */
    private function ingredients(array $lines, array &$warnings): array
    {
        /** @var array<string, ParsedIngredient> $byName */
        $byName = [];
        $headerSeen = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if (! str_starts_with($line, '|') || preg_match('/^[\s|:\-]+$/u', $line) === 1) {
                continue;
            }

            if (! $headerSeen) {
                $headerSeen = true;

                continue;
            }

            $cells = array_map('trim', explode('|', trim($line, '|')));
            $name = Ingredient::normalizeName($cells[0]);
            $rawQuantity = $cells[1] ?? '';

            if ($name === '') {
                if ($rawQuantity !== '') {
                    $warnings[] = "pominięto wiersz bez nazwy składnika (ilość „{$rawQuantity}”)";
                }

                continue;
            }

            $name = mb_ucfirst($name);
            $quantity = $this->quantity($rawQuantity);

            if (is_string($quantity)) {
                $warnings[] = "pominięto składnik „{$name}”: {$quantity}";

                continue;
            }

            $key = mb_strtolower($name);
            [$amount, $unitCode] = $quantity;

            if ($unitCode === 'szt' && bccomp($amount, self::SUSPICIOUS_PIECES, 2) >= 0 && preg_match('/^[\d.,\/\s]+$/u', $rawQuantity) === 1) {
                $warnings[] = "składnik „{$name}”: „{$rawQuantity}” bez jednostki potraktowano jako sztuki (może brakować „g”?)";
            }

            if (! isset($byName[$key])) {
                $byName[$key] = new ParsedIngredient($name, $amount, $unitCode);

                continue;
            }

            $existing = $byName[$key];

            if ($existing->unitCode !== $unitCode) {
                $warnings[] = "składnik „{$name}” występuje dwa razy w różnych jednostkach; zostawiono pierwszy wiersz";

                continue;
            }

            $byName[$key] = new ParsedIngredient($existing->name, bcadd($existing->quantity, $amount, 2), $unitCode);
            $warnings[] = "składnik „{$name}” występuje dwa razy; ilości zsumowano";
        }

        return array_values($byName);
    }

    /**
     * Converts "250 g", "160g", "0,5 łyżeczki", "1/2 szklanki", "3" (pieces) or "szczypta" (one pinch).
     *
     * @return array{0: numeric-string, 1: string}|string The quantity and unit code, or the reason it was rejected.
     */
    private function quantity(string $raw): array|string
    {
        if ($raw === '') {
            return 'brak ilości';
        }

        if (preg_match('/^(?<number>\d+(?:[.,]\d+)?(?:\s*\/\s*\d+)?)?\s*(?<unit>\p{L}+)?\.?$/u', $raw, $matches) !== 1
            || (($matches['number'] ?? '') === '' && ($matches['unit'] ?? '') === '')) {
            return "nieczytelna ilość „{$raw}”";
        }

        $number = $matches['number'] ?? '';
        $unit = mb_strtolower($matches['unit'] ?? '');
        [$unitCode, $multiplier] = $unit === '' ? ['szt', '1'] : (self::UNIT_ALIASES[$unit] ?? [null, '1']);

        if ($unitCode === null) {
            return "nieznana jednostka „{$unit}”";
        }

        $amount = $number === '' ? '1' : $this->decimal($number);

        if ($amount === null) {
            return "nieczytelna ilość „{$raw}”";
        }

        $quantity = bcadd(bcmul($amount, $multiplier, self::SCALE), '0.005', 2);

        if (bccomp($quantity, '0.01', 2) < 0) {
            return "ilość „{$raw}” jest mniejsza niż 0,01";
        }

        return [$quantity, $unitCode];
    }

    /**
     * @return numeric-string|null
     */
    private function decimal(string $number): ?string
    {
        $number = str_replace([',', ' '], ['.', ''], $number);

        if (! str_contains($number, '/')) {
            return is_numeric($number) ? $number : null;
        }

        [$numerator, $denominator] = explode('/', $number, 2);

        if (! is_numeric($numerator) || ! is_numeric($denominator) || bccomp($denominator, '0', self::SCALE) === 0) {
            return null;
        }

        return bcdiv($numerator, $denominator, self::SCALE);
    }

    /**
     * Steps without list markers, one per line, followed by the source when the file names one.
     *
     * @param  list<string>  $lines
     * @param  list<string>  $warnings
     * @param  list<string>  $errors
     */
    private function instructions(array $lines, string $source, array &$warnings, array &$errors): string
    {
        $steps = [];

        foreach ($lines as $line) {
            $step = trim((string) preg_replace('/^\s*(?:[-*+]|\d+[.)])\s+/u', '', $line));

            if ($step !== '') {
                $steps[] = $step;
            }
        }

        if ($steps === []) {
            $errors[] = 'brak sposobu przygotowania';

            return '';
        }

        $isPlaceholder = array_filter($steps, fn (string $step): bool => preg_match('/^według\b/iu', $step) !== 1) === [];

        if ($isPlaceholder) {
            $warnings[] = 'sposób przygotowania to tylko odnośnik („'.$steps[0].'”)';
        }

        $instructions = implode("\n", $steps);

        return $source === '' ? $instructions : "{$instructions}\n\nŹródło: {$source}";
    }

    /**
     * @param  numeric-string|null  $default
     * @param  list<string>  $warnings
     * @param  list<string>  $errors
     * @return numeric-string|null
     */
    private function calories(?string $value, ?string $default, array &$warnings, array &$errors): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            if ($default === null) {
                $errors[] = 'brak kaloryczności („kalorie:” w nagłówku, kcal całego przepisu, albo opcja --calories)';

                return null;
            }

            $warnings[] = "użyto domyślnej kaloryczności {$default} kcal";

            return $default;
        }

        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(?:kcal)?$/iu', $value, $matches) !== 1) {
            $errors[] = "nieczytelna kaloryczność „{$value}”";

            return null;
        }

        $calories = str_replace(',', '.', $matches[1]);

        if (bccomp($calories, '50', 2) < 0) {
            $errors[] = "kaloryczność całego przepisu musi wynosić co najmniej 50 kcal (jest {$value})";

            return null;
        }

        return $calories;
    }

    /**
     * @param  list<string>  $warnings
     * @param  list<string>  $errors
     */
    private function servings(?string $value, ?int $default, array &$warnings, array &$errors): ?int
    {
        $value = trim((string) $value);

        if ($value === '') {
            if ($default === null) {
                $errors[] = 'brak liczby porcji („porcje:” w nagłówku albo opcja --servings)';

                return null;
            }

            $warnings[] = "użyto domyślnej liczby porcji {$default}";

            return $default;
        }

        if (preg_match('/^[1-9]\d*$/', $value) !== 1) {
            $errors[] = "liczba porcji musi być dodatnią liczbą całkowitą (jest „{$value}”)";

            return null;
        }

        return (int) $value;
    }
}
