<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogsMealPlan extends Model
{
    protected $table = 'logs_meal_plan';

    public $timestamps = false;

    protected $fillable = [
        'meal_plan_id',
        'started_at',
        'finished_at',
        'status',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'created_at' => 'timestamp',
    ];

    public function mealPlan(): BelongsTo
    {
        return $this->belongsTo(MealPlan::class);
    }
}
