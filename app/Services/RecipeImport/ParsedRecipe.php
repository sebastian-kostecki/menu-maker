<?php

declare(strict_types=1);

namespace App\Services\RecipeImport;

/**
 * Result of parsing one Markdown recipe file: the data to store plus what the parser skipped or rejected.
 */
final readonly class ParsedRecipe
{
    /**
     * @param  'breakfast'|'supper'|'dinner'|null  $category
     * @param  numeric-string|null  $calories  Total kcal of the whole recipe.
     * @param  list<ParsedIngredient>  $ingredients
     * @param  list<string>  $warnings  Problems that do not block the import (a skipped row, a placeholder instruction).
     * @param  list<string>  $errors  Problems that make the recipe impossible to import.
     */
    public function __construct(
        public string $name,
        public ?string $category,
        public string $instructions,
        public ?string $calories,
        public ?int $servings,
        public array $ingredients,
        public array $warnings,
        public array $errors,
    ) {}

    public function isImportable(): bool
    {
        return $this->errors === [];
    }
}
