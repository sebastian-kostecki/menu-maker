<?php

declare(strict_types=1);

namespace App\Services\IngredientScaling;

/**
 * What scaling did to a plan, stored in `generation_meta.scaling`.
 */
final readonly class ScalingOutcome
{
    public const STATUS_COMPUTED = 'computed';

    public const STATUS_REFINED = 'refined';

    public const STATUS_AS_WRITTEN = 'as_written';

    /**
     * @param  list<string>  $unscaledRecipes  Unique names of recipes without kcal, Polish-sorted.
     * @param  list<string>  $membersWithoutBody  First names of adults on the reference row.
     */
    public function __construct(
        public string $status,
        public array $unscaledRecipes = [],
        public array $membersWithoutBody = [],
    ) {}

    /**
     * @return array{status: string, unscaled_recipes: list<string>, members_without_body: list<string>}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'unscaled_recipes' => $this->unscaledRecipes,
            'members_without_body' => $this->membersWithoutBody,
        ];
    }
}
