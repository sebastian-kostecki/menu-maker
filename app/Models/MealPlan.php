<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealPlan extends Model
{
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
}
