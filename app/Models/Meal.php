<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\Recipe|null $recipe
 */
class Meal extends Model
{
    protected $fillable = [
        'meal_plan_id',
        'recipe_id',
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
        return $this->belongsTo(Recipe::class);
    }
}
