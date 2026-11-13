<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    /**
     * @var list<string>
     */
    public const ALLOWED_CODES = ['g', 'kg', 'ml', 'l', 'szt'];

    protected $fillable = [
        'code',
        'conversion_factor_to_base',
    ];

    protected $casts = [
        'conversion_factor_to_base' => 'decimal:4',
    ];

    /**
     * @return HasMany<RecipeIngredient, $this>
     */
    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }
}
