<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int|null $recipe_id
 * @property int|null $feeds_meal_id
 * @property-read MealPlan $mealPlan
 * @property-read Recipe|null $recipe
 * @property-read Collection<int, MealIngredient> $ingredients
 */
class Meal extends Model
{
    protected $fillable = [
        'meal_plan_id',
        'recipe_id',
        'feeds_meal_id',
        'meal_date',
        'meal_category',
    ];

    protected $casts = [
        'meal_date' => 'date',
    ];

    public function mealPlan(): BelongsTo
    {
        return $this->belongsTo(MealPlan::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class)->withTrashed();
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(MealIngredient::class)->orderBy('id');
    }

    public function feedsMeal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'feeds_meal_id');
    }

    public function fedBy(): HasOne
    {
        return $this->hasOne(self::class, 'feeds_meal_id');
    }
}
