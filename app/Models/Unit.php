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

    /**
     * Unit code to the group of codes that can be summed together.
     *
     * @var array<string, string>
     */
    public const GROUPS = [
        'g' => 'mass',
        'kg' => 'mass',
        'ml' => 'volume',
        'l' => 'volume',
        'szt' => 'pieces',
    ];

    /**
     * Group display order for rows with the same ingredient name.
     *
     * @var list<string>
     */
    public const GROUP_ORDER = ['mass', 'volume', 'pieces'];

    /**
     * Base code per group (the code the exact sum is expressed in).
     *
     * @var array<string, string>
     */
    public const GROUP_BASE_CODES = [
        'mass' => 'g',
        'volume' => 'ml',
        'pieces' => 'szt',
    ];

    /**
     * Optional large display code per group, used when the base sum exceeds LARGE_UNIT_THRESHOLD.
     *
     * @var array<string, string>
     */
    public const GROUP_LARGE_CODES = [
        'mass' => 'kg',
        'volume' => 'l',
    ];

    public const LARGE_UNIT_THRESHOLD = '1000';

    /**
     * Polish display labels per unit code.
     *
     * @var array<string, string>
     */
    public const LABELS = [
        'g' => 'g',
        'kg' => 'kg',
        'ml' => 'ml',
        'l' => 'l',
        'szt' => 'szt.',
    ];

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
