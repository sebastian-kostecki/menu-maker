<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recipe extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'category',
        'instructions',
        'calories',
        'servings',
    ];

    protected $casts = [
        'calories' => 'decimal:2',
        'servings' => 'integer',
    ];

    /**
     * Default relationships to eager load.
     *
     * @var array<int, string>
     */
    protected $with = [];

    /**
     * Scope for filtering by user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for filtering by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for searching by name and instructions.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('instructions', 'like', "%{$search}%");
        });
    }

    /**
     * Scope for ordering by common fields.
     */
    public function scopeOrderByField($query, string $field, string $direction = 'desc')
    {
        $allowedFields = ['name', 'created_at', 'calories', 'category'];

        if (in_array($field, $allowedFields)) {
            return $query->orderBy($field, $direction);
        }

        return $query->orderBy('created_at', 'desc');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'recipe_ingredients')
            ->using(RecipeIngredient::class)
            ->withPivot(['quantity', 'unit_id'])
            ->withTimestamps();
    }

    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class);
    }
}
