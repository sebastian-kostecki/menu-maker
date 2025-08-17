<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Meal> $meals
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LogsMealPlan> $logs
 */
class MealPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'status',
        'generation_meta',
        'pdf_path',
        'pdf_size',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'generation_meta' => 'array',
        'pdf_size' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LogsMealPlan::class);
    }

    /**
     * Scope query to only include meal plans owned by the given user.
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
