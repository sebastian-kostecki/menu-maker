<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    protected $fillable = [
        'name',
        'is_catalog',
    ];

    protected $casts = [
        'is_catalog' => 'boolean',
    ];

    /**
     * Catalog ingredients plus those used in the user's non-deleted recipes.
     *
     * @param  Builder<Ingredient>  $query
     * @return Builder<Ingredient>
     */
    public function scopeSuggestedFor(Builder $query, int $userId): Builder
    {
        return $query
            ->where(function (Builder $inner) use ($userId): void {
                $inner->where('ingredients.is_catalog', true)
                    ->orWhereIn('ingredients.id', function ($sub) use ($userId): void {
                        $sub->select('recipe_ingredients.ingredient_id')
                            ->from('recipe_ingredients')
                            ->join('recipes', 'recipes.id', '=', 'recipe_ingredients.recipe_id')
                            ->where('recipes.user_id', $userId)
                            ->whereNull('recipes.deleted_at');
                    });
            })
            ->orderBy('ingredients.name');
    }

    /**
     * Trim and collapse internal whitespace to single spaces.
     */
    public static function normalizeName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    /**
     * Find or create the ingredient for a name, case-insensitively, without races.
     */
    public static function resolveIdByName(string $name): int
    {
        $normalized = self::normalizeName($name);
        $now = now();

        self::query()->insertOrIgnore([
            'name' => $normalized,
            'is_catalog' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) self::query()
            ->whereRaw('lower(name) = lower(?)', [$normalized])
            ->value('id');
    }

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'recipe_ingredients')
            ->using(RecipeIngredient::class)
            ->withPivot(['quantity', 'unit_id'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<RecipeIngredient, $this>
     */
    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }
}
