<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ingredient of one generated meal: the recipe amount, the code-computed amount and the final amount.
 *
 * @property string $quantity
 * @property-read Ingredient|null $ingredient
 * @property-read Unit|null $unit
 */
class MealIngredient extends Model
{
    public const SOURCE_AI = 'ai';

    public const SOURCE_COMPUTED = 'computed';

    public const SOURCE_AS_WRITTEN = 'as_written';

    protected $fillable = [
        'meal_id',
        'ingredient_id',
        'unit_id',
        'recipe_quantity',
        'computed_quantity',
        'quantity',
        'source',
    ];

    protected $casts = [
        'recipe_quantity' => 'decimal:2',
        'computed_quantity' => 'decimal:2',
        'quantity' => 'decimal:2',
    ];

    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
